<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/core.php';
function check_insight(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
check_insight(!teamfight_player_participated(['gold_delta'=>100, 'xp_delta'=>500, 'healing'=>800, 'buybacks'=>1, 'ability_uses'=>['farm'=>9], 'item_uses'=>['tango'=>2]]), 'Passive income, self-healing and generic casts do not establish participation');
check_insight(teamfight_player_participated(['damage_taken'=>10]), 'Victim with no outgoing damage is included');
check_insight(teamfight_player_participated(['participated'=>true, 'combat_healing'=>80]), 'Confirmed healer is included');
check_insight(!teamfight_player_participated(['participated'=>false, 'damage'=>100]), 'Explicit parser flag overrides broad interval totals');
check_insight(damage_map_total([], 'damage') === null, 'Missing data is not zero');
check_insight(damage_map_total(['damage'=>[]], 'damage') === 0, 'An explicit empty map is zero');
$heroes = [1=>['name'=>'npc_dota_hero_one','localized_name'=>'Hero One'], 2=>['name'=>'npc_dota_hero_two','localized_name'=>'Hero Two']];
check_insight(damage_map_total(['damage'=>['npc_dota_hero_two'=>15,'illusion_npc_dota_hero_two'=>99]], 'damage', [['hero_id'=>2]], $heroes) === 15, 'Hero matrix excludes illusions');
$r = [['player_slot'=>0,'hero_id'=>1,'personaname'=>'Active Player']];
$d = [['player_slot'=>128,'hero_id'=>2,'personaname'=>'Inactive Farmer']];
$slots = index_players_by_slot_for_teamfights($r,$d);
$positions = build_teamfight_position_index($r,$d);
$b = analyze_teamfight(['start'=>20,'end'=>35,'players'=>[['participated'=>true,'damage'=>80,'gold_delta'=>20],['participated'=>false,'gold_delta'=>900,'healing'=>100]]],0,$slots,$positions,$heroes);
check_insight(count($b['participants']) === 1, 'Inactive players excluded from the participant list');
check_insight($b['dire_gold'] === 0, 'Inactive players do not affect displayed totals');
ob_start(); render_teamfight_card($b,$heroes); $html = ob_get_clean();
check_insight(!str_contains($html,'Inactive Farmer'), 'Inactive player absent from rendered HTML');
check_insight(str_contains($html,'Active Player'), 'Active player present in rendered HTML');
check_insight(str_contains($html,'data-participated="true"'), 'Participation is explicit in markup');
ob_start(); render_damage_tab([['hero_id'=>1,'personaname'=>'Missing metrics']], [], $heroes); $fallback = ob_get_clean();
check_insight(substr_count($fallback, 'Нет данных') === 3, 'Missing fallback metrics stay unavailable, not zero');
check_insight(!str_contains($fallback, 'insight-jump-links'), 'Missing-data state has no dead navigation anchors');
$native = ['damage_reduction_source'=>'replay_team_damage_counters','damage_received_pre_reduction'=>131612,'damage_received_post_reduction'=>58490];
$m = damage_reduction_metrics($native);
check_insight(damage_percent($m['pct']) === '55,6 %', 'Native counters render the reference percentage');
check_insight($m['prevented'] === 73122, 'Prevented damage equals before minus after');
check_insight(damage_reduction_metrics([]) === null, 'Old data is not fabricated');
check_insight(damage_reduction_metrics(array_merge($native,['damage_received_pre_reduction'=>-1])) === null, 'Invalid native counters are unavailable');
check_insight(damage_reduction_metrics(array_merge($native,['damage_received_pre_reduction'=>0,'damage_received_post_reduction'=>0]))['pct'] === null, 'Zero input has no defined percentage');
check_insight(damage_reduction_metrics(array_merge($native,['damage_received_pre_reduction'=>100,'damage_received_post_reduction'=>120]))['pct'] < 0, 'Damage amplification is not clamped');
ob_start(); render_damage_tab([array_merge(['hero_id'=>1,'personaname'=>'Native Player','damage'=>['npc_dota_hero_two'=>15],'damage_taken'=>[]],$native)],[],$heroes);$native_html=ob_get_clean();
check_insight(str_contains($native_html,'55,6 %') && str_contains($native_html,'131 612') && str_contains($native_html,'73 122'), 'Native before, prevented and percentage all reach HTML');
echo "PASS: 21 match-insight checks\n";

// Teamfight details: missing data, replay order, icons and safe legacy fallback.
check_insight($b['participants'][0]['damage_taken'] === null, 'Missing fight incoming damage stays unavailable');
$chronology = ['start'=>20,'end'=>35,'actions_version'=>1,'actions'=>[
 ['time'=>21,'sequence'=>8,'slot'=>1,'kind'=>'ability','key'=>'dragon_knight_breathe_fire','target_slot'=>0],
 ['time'=>21,'sequence'=>7,'slot'=>1,'kind'=>'item','key'=>'blink','target_slot'=>null],
 ['time'=>40,'sequence'=>9,'slot'=>1,'kind'=>'item','key'=>'blink'],
 ['time'=>21,'sequence'=>10,'slot'=>1,'kind'=>'item','key'=>'<script>'],
]];
$normalized = normalize_teamfight_actions($chronology,[0=>0,1=>128]);
check_insight(count($normalized)===2 && $normalized[0]['key']==='blink', 'Chronology bounded by episode; equal-second source order retained');
check_insight($normalized[1]['slot']===128 && $normalized[1]['target_slot']===0, 'Chronology maps positional slots to real players');
check_insight(normalize_teamfight_actions([],[])===null, 'Aggregate-only legacy data is not an invented chronology');
check_insight(str_contains($html,'Для времени и последовательности нужен повторный разбор'), 'Legacy chronology has a reprocessing explanation');
$icon=teamfight_action_icon('blink','item',[],2);
check_insight(str_contains($icon,'items/blink.png') && str_contains($icon,'×2'), 'Item icons retain use counts');
$active = analyze_teamfight(array_merge($chronology,['players'=>[['participated'=>true,'damage_taken'=>0,'damage'=>80],['participated'=>true,'damage_taken'=>80,'damage'=>0]]]),0,$slots,$positions,$heroes);
check_insight($active['participants'][0]['damage_taken']!==null, 'Explicit zero incoming damage is available');
ob_start();render_teamfight_card($active,$heroes);$new_html=ob_get_clean();
check_insight(str_contains($new_html,'data-sequence="7"') && str_contains($new_html,'data-sequence="8"'), 'Actual source sequence reaches HTML');
check_insight(str_contains($new_html,'Получено') && str_contains($new_html,'100% команды'), 'Incoming damage and team share reach table');
check_insight(!str_contains($new_html,'Частые действия'), 'Obsolete spacious action summaries removed');
echo "PASS: 10 teamfight detail checks\n";
