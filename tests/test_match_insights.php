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
echo "PASS: 14 match-insight checks\n";
