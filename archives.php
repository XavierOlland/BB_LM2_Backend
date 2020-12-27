<?php

function archives_standings($con, $id){
    $standings = [];
    $sqlStandings = "SELECT * FROM site_competitions_standings WHERE competition_id = $id";
    $resultStandings = $con->query($sqlStandings);
    while($dataStandings = $resultStandings->fetch_assoc()) {
        array_push($standings, $dataStandings);
    }
    return $standings;
};

?>
