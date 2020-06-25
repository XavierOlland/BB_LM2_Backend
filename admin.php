<?php

$action = $_GET["action"];

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

define('PHPBB_ROOT_PATH','./../Forum/');

include('vue-config.php');

$postdata = file_get_contents("php://input");
$params = json_decode($postdata);

include('competition_vue.php');
include('season.php');
include('forum.php');
include('player.php');
include('team.php');


switch ($action) {
  case "forumProfilesUpdate":
    forum_profiles_update($con);
    break;
  case "getIngameCompetitions":
    ingame_competition_fetch_all($con, $Cyanide_Key, $Cyanide_League);
    break;
  case "getCompetitionsForums":
    forum_fetch_all($con,$params[0]);
    break;
  case "competitionAdd":
    competition_add($con, $Cyanide_Key, $params);
    forum_links_add($con, $params);
    break;
  case "seasonArchive":
    champion_set($con);
    season_archive($con);
    break;
  default:
    echo "Erreur!";
    break;
};


?>
