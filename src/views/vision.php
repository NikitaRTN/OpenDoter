<?php
declare(strict_types=1);
?>
<link rel="stylesheet" href="<?php echo e(asset_url('src/assets/laning.css')); ?>">
<?php
render_laning_page($match, $radiant_players, $dire_players, $heroes);
