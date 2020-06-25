<?php

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
    // you want to allow, and if so:
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        // may also be using PUT, PATCH, HEAD etc
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}


header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Headers: X-Requested-With');
define('PHPBB_ROOT_PATH','./../Forum/');

//This code allows use of phpBB identification and the link between the manager and the forum.
//Utilisation de l'authentification phpBB, lien entre le manager et le forum
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../Forum/';


$phpEx = substr(strrchr(__FILE__, '.'), 1);

include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
include($phpbb_root_path . 'config.' . $phpEx);

//Create the mandatory variables / Création des variables nécessaires
$user->session_begin();
$auth->acl($user->data);
$user->setup();
$admin = $user->data['group_id']==10 ? 1 : 0;

//MySQL connection / Connexion MySQL
$con = mysqli_connect($dbhost,$dbuser,$dbpasswd,$dbname);
if (!$con) { die('Could not connect: ' . mysqli_error()); }
mysqli_set_charset($con,'utf8');
$coach = $con->query("SELECT id, cyanide_id, active, gold FROM site_coachs WHERE user_id=".$user->data['user_id']);
$coach = $coach->fetch_assoc();

$user->data['coach'] = $coach;
$request->enable_super_globals();
include('vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$request->disable_super_globals();

$Cyanide_Key = $_ENV['CYANIDE_API_KEY'];
$Cyanide_League = $_ENV['CYANIDE_LEAGUE'];

?>
