<?php
declare(strict_types=1);
require_once __DIR__.'/../src/core.php';
$raw=[];
for($i=1;$i<=20;$i++)$raw[]=['hero_id'=>$i,'games'=>21-$i,'win'=>1];
$s=compute_player_stats([],[],[], $raw);
if(count($s['top_heroes'])!==20)throw new RuntimeException('Lifetime hero list truncated');
$rows=[];
for($i=1;$i<=15;$i++)$rows[]=['hero_id'=>$i,'player_slot'=>0,'radiant_win'=>true,'kills'=>1,'deaths'=>1,'assists'=>1,'duration'=>1200];
$s=compute_player_stats($rows,[],[],[]);
if(count($s['top_heroes'])!==15)throw new RuntimeException('Fallback hero list truncated');
ob_start();render_player_profile(['profile'=>['account_id'=>123,'personaname'=>'Fixture']],[],[],['top_heroes'=>$s['top_heroes'],'include_turbo'=>true]);$html=ob_get_clean();
if(strpos($html,'data-profile-workspace')===false||strpos($html,'data-profile-filters')===false)throw new RuntimeException('Interactive workspace missing');
if(substr_count($html,'data-hero-name=')!==15)throw new RuntimeException('Not all heroes rendered');
if(strpos($html,'data-match-breakdown')>strpos($html,'id="profile-heroes"'))throw new RuntimeException('Score details must stay by match history');
echo "Profile PHP regressions PASS (5 checks)\n";
