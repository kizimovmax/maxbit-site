<?php
/** Обёртка для WP-CLI: php vendor/wp-cli.phar --path=site --allow-root eval-file scripts/seed.php  (логика — в модуле: includes/seed.php) */
if ( ! defined( 'ABSPATH' ) ) { exit( "Запускайте через wp eval-file\n" ); }
maxbit_seed_run();
