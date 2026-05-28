import sys
import json
import socket
import time
import http.client
import logging

# 1. Включаем подробную отладку сокетов и HTTP на уровне интерпретатора
print("[DEBUG] Настройка подробного логирования...")
http.client.HTTPConnection.debuglevel = 1

logging.basicConfig()
logging.getLogger().setLevel(logging.DEBUG)

# Конфигурация
url_host = "127.0.0.1"  # Используем явный IPv4 адрес вместо localhost
url_port = 20128
path = "/v1/chat/completions"
model_id = "free-stack"
api_key = "none"  # Замените на ваш ключ OmniRoute, если он настроен
timeout_seconds = 15  # Предел ожидания, чтобы скрипт не зависал навсегда

print(f"[DEBUG] Параметры запроса:")
print(f"  - Хост: {url_host}:{url_port}")
print(f"  - Путь: {path}")
print(f"  - Модель: {model_id}")
print(f"  - Лимит времени (таймаут): {timeout_seconds} сек.\n")

# Тело запроса
payload = {
    "model": model_id,
    "messages": [
        {"role": "user", "content": "Hi"}
    ],
    "temperature": 0.3
}
data_bytes = json.dumps(payload).encode("utf-8")

# Заголовки (явно отключаем keep-alive для изоляции теста)
headers = {
    "Content-Type": "application/json",
    "Authorization": f"Bearer {api_key}",
    "Connection": "close"
}

# --- ШАГ 1: Тест физического подключения сокета ---
print("[DEBUG] ШАГ 1: Проверка доступности порта (TCP-соединение)...")
try:
    start_socket = time.time()
    s = socket.create_connection((url_host, url_port), timeout=5)
    s.close()
    print(f"[OK] Порт {url_port} открыт. Подключение установлено за {time.time() - start_socket:.4f} сек.\n")
except Exception as e:
    print(f"[FAIL] Не удалось подключиться к порту {url_port}. Сервер OmniRoute запущен?")
    print(f"Ошибка сокета: {e}")
    sys.exit(1)

# --- ШАГ 2: Отправка HTTP-запроса и чтение ответа ---
print("[DEBUG] ШАГ 2: Отправка запроса через http.client...")
conn = None
try:
    # Инициализируем соединение с жестким таймаутом
    conn = http.client.HTTPConnection(url_host, url_port, timeout=timeout_seconds)
    
    print("[HTTP ->] Установка TCP соединения...")
    conn.connect()
    
    print("[HTTP ->] Отправка HTTP-заголовков...")
    conn.putrequest("POST", path)
    for header, value in headers.items():
        conn.putheader(header, value)
    conn.putheader("Content-Length", str(len(data_bytes)))
    conn.endheaders()
    
    print(f"[HTTP ->] Отправка тела запроса ({len(data_bytes)} байт)...")
    conn.send(data_bytes)
    
    print("\n[HTTP <-] Ожидание заголовков ответа от OmniRoute...")
    start_response = time.time()
    
    # Скрипт попытается прочитать ответ. Если зависнет здесь — сработает таймаут
    response = conn.getresponse()
    
    print(f"\n[OK] Ответ получен за {time.time() - start_response:.2f} сек.")
    print(f"Код состояния HTTP: {response.status} {response.reason}")
    
    print("\n[HTTP <-] Чтение тела ответа...")
    response_data = response.read().decode("utf-8")
    print(f"Тело ответа:\n{response_data}")

except socket.timeout:
    print(f"\n[TIMEOUT] Превышено время ожидания ответа ({timeout_seconds} сек).")
    print("Возможные причины:")
    print("  1. Зацикливание роутинга: проверьте, не ссылается ли комбо 'free-stack' само на себя.")
    print("  2. Зависание Cloudflare: провайдер принял запрос, но не может его обработать.")
    print("  3. Посмотрите в терминал, где запущен сам 'omniroute' — какие логи выводятся там?")
except Exception as e:
    print(f"\n[FAIL] Произошла ошибка во время HTTP-сессии: {e}")
finally:
    if conn:
        conn.close()
        print("\n[DEBUG] Соединение закрыто.")