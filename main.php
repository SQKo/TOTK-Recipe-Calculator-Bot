<?php

/*
 * This file is a part of the TOTK Recipe Calculator project.
 *
 * Copyright (c) 2023-present Valithor Obsidion <valzargaming@gmail.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */


 use \TOTK\TOTK;
 use \TOTK\Stats;
 use \Discord\Discord;
 use \Discord\Helpers\CacheConfig;
 use \React\EventLoop\Loop;
 use \WyriHaximus\React\Cache\Redis as RedisCache;
 use \Clue\React\Redis\Factory as Redis;
 use \Monolog\Logger;
 use \Monolog\Level;
 use \Monolog\Formatter\LineFormatter;
 use \Monolog\Handler\StreamHandler;
 use \Discord\WebSockets\Intents;
 use \React\Http\Browser;

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1'); //Unlimited memory usage
if (! @include getcwd() . '/vendor/autoload.php') {
    include __DIR__ . '/src/TOTK/totk.php';
    include __DIR__ . '/src/TOTK/stats_object.php';
    include __DIR__ . '/src/TOTK/variable_functions.php';
    include __DIR__ . '/src/TOTK/functions.php';
}
include __DIR__ . '/src/TOTK/variable_functions.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

set_time_limit(0);
ignore_user_abort(1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1'); //Unlimited memory usage
define('MAIN_INCLUDED', 1); //Token and SQL credential files may be protected locally and require this to be defined to access
require getcwd() . '/token.php'; //$token

$loop = Loop::get();
$streamHandler = new StreamHandler('php://stdout', Level::Debug);
$streamHandler->setFormatter(new LineFormatter(null, null, true, true));
$logger = new Logger('TOTK', [$streamHandler]);
$discord = new Discord([
    'loop' => $loop,
    'logger' => $logger,
    /* //Disabled for debugging
    'cache' => new CacheConfig(
        $interface = new RedisCache(
            (new Redis($loop))->createLazyClient('127.0.0.1:6379'),
            'dphp:cache:
        '),
        $compress = true, // Enable compression if desired
        $sweep = false // Disable automatic cache sweeping if desired
    ), 
    */
    /*'socket_options' => [
        'dns' => '8.8.8.8', // can change dns
    ],*/
    'token' => $token,
    'loadAllMembers' => true,
    'storeMessages' => true, //Because why not?
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::MESSAGE_CONTENT,
]);
$stats = new Stats();
$stats->init($discord);

$options = array(
    'loop' => $loop,
    'discord' => $discord,
    'logger' => $logger,
    'stats' => $stats,
    
    //Configurations
    'github' => 'https://github.com/VZGCoders/TOTK-Recipe-Calculator-Bot/',
    'command_symbol' => '@TOTK',
    'owner_id' => '68828609288077312', //Rattlecat
    'technician_id' => '116927250145869826', //Valithor
    'totk_guild_id' => '1017158025770967133', //The First Oven
    'files' => array(
        'status_path' => 'status.txt',
    ),
    'channel_ids' => array(),
    'role_ids' => array(),
    'functions' => array(
        'ready' => [
            //'on_ready' => $on_ready,
            'status_changer_timer' => $status_changer_timer,
            'status_changer_random' => $status_changer_random,
        ],
        'message' => [
            'on_message' => $on_message,
        ],
    ),
);
if (@include 'totk_token.php') $options['totk_token'] = $TOTK_token; //NYI
$TOTK = new TOTK($options);
if (! @include getcwd() . '/vendor/vzgcoders/TOTK/autoload.php') {
    include __DIR__ . '/src/TOTK/webapi.php';
}
$TOTK->run();


/*
use \TOTK\Parts\Ingredient;
use \TOTK\Crafter;
use \TOTK\Helpers\Collection;

$crafter = new Crafter();

if (! $materials_file = file(__DIR__ . '\vendor\vzgcoders\totk-recipe-calculator\src\TOTK\CSVs\materials.csv')) $materials_file = file(__DIR__ . '\src\TOTK\CSVs\materials.csv');
$csv = array_map('str_getcsv', $materials_file);
$keys = array_shift($csv);
$materials = array();
foreach ($csv as $row) $materials[] = array_combine($keys, $row);
$materials_collection = new Collection([], $keys[2]);
foreach ($materials as $array) $materials_collection->pushItem($array);

$ingredient1 = new Ingredient($materials_collection->get('Euen name', 'Apple'));
$ingredient2 = new Ingredient($materials_collection->get('Euen name', 'Wildberry'));
$ingredient3 = new Ingredient($materials_collection->get('Euen name', 'Cane Sugar'));
$ingredient4 = new Ingredient($materials_collection->get('Euen name', 'Tabantha Wheat'));
$ingredient5 = null;



$ingredients = [$ingredient1 ?? NULL, $ingredient2 ?? NULL, $ingredient3 ?? NULL, $ingredient4 ?? NULL, $ingredient5 ?? NULL];
//var_dump('[INGREDIENTS]', $ingredients);

var_dump('[MEAL]', $meal = $crafter->process($ingredients));

//$recipe = new Recipe($meal, $ingredients);
//var_dump('[RECIPE]', $recipe); //Recipe needs to be fixed to remove the hardcoded stuff like Rock Hard and Dubious Food, because we find out what the actual meal output is by using the crafter->process() method
//var_dump('POSSIBLE MEAL', $meal = $result[0]);
*/