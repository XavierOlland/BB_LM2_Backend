<?php
function season_archive($con){

    // $sqlCompetitions = "UPDATE site_competitions SET active=0";
    // $con->query($sqlCompetitions);
    //
    // $sqlTeams = "UPDATE site_teams SET active=0";
    // $con->query($sqlTeams);
    //
    // $sqlCoachs = "UPDATE site_coachs SET active=0";
    // $con->query($sqlCoachs);

    $json = new stdClass;
    $json->type = "success";
    $json->text = "Saison archivée.";
    echo json_encode($json);

}

function champion_set($con){

    $champion = new stdClass();
    $sqlCompetitions = "SELECT id, season FROM site_competitions WHERE active=1 AND champion=1";

    $resultCompetitions = $con->query($sqlCompetitions);
    $competition = $resultCompetitions->fetch_object();
    $standing = competition_standing($con, $competition->id);
    $sqlTeam = "SELECT * FROM site_teams WHERE id=".$standing[0]['id'];
    $resultTeam = $con->query($sqlTeam);
    $team = $resultTeam->fetch_object();

    $champion->coach = coach_fetch($con, $team->coach_id);
     $champion->colours = [
         $team->color_1,
         $team->color_2
    ];
    $champion->competition = new stdClass();
    $champion->competition->id = $competition->id;
    $champion->competition->name = $competition->season;
    $champion->logo = $team->logo;
    $champion->race = $team->param_id_race;
    $champion->team = $team->name;

    $sqlChampion = "UPDATE site_parameters SET translation='".json_encode($champion,JSON_NUMERIC_CHECK)."' WHERE type='bbbl_champion'";
    $con->query($sqlChampion);

}
?>
