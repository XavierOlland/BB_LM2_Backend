<?php

//Create player
function player_create($con, $bbblID, $teamID, $player){
    if( $player->id ){
      $sqlCreate = "INSERT INTO site_players (cyanide_id, team_id, cyanide_id_team, param_name_type, name, level, xp, attributes, skills, dead, casualties)
      VALUES (".$player->id.",".$bbblID.",".$teamID.",'".$player->type."','".str_replace("'","\'",$player->name)."',".$player->level.",".$player->xp.",'".json_encode($player->attributes)."','".json_encode($player->skills)."',0,'".json_encode($player->casualties_state)."')";
      $con->query($sqlCreate);
    };
};

//Update player
function player_update($con, $bbblID, $teamID, $player){
    $sqlUpdate = "UPDATE site_players SET
      team_id = ".$bbblID.",
      cyanide_id_team = ".$teamID.",
      level = ".$player->level.",
      xp = CASE WHEN ".$player->xp." > 0 THEN ".$player->xp." ELSE xp END,
      attributes = '".json_encode($player->attributes)."',
      skills = '".json_encode($player->skills)."',
      casualties = '".json_encode($player->casualties_state)."',
      fired = 0,
      dead = IFNULL('".$player->stats->sustaineddead."',0)
      WHERE cyanide_id = ".$player->id;
    $con->query($sqlUpdate);
};

//Fire a player
function player_fire($con, $cyanideID){
    $sqlFire = "UPDATE site_players SET fired = 1 WHERE cyanide_id=".$cyanideID;
    $con->query($sqlFire);
};

//Get player's stats
function player_stats_fetch($con, $playerID){
      $sqlStats = "SELECT IFNULL(SUM(s.matchplayed),0) AS matchplayed, IFNULL(SUM(s.mvp),0) AS mvp, IFNULL(SUM(s.inflictedpasses),0) AS inflictedpasses, IFNULL(SUM(s.inflictedcatches),0) AS inflictedcatches, IFNULL(SUM(s.inflictedinterceptions),0) AS inflictedinterceptions, IFNULL(SUM(s.inflictedtouchdowns),0) AS inflictedtouchdowns, IFNULL(SUM(s.inflictedcasualties),0) AS inflictedcasualties, IFNULL(SUM(s.inflictedstuns),0) AS inflictedstuns, IFNULL(SUM(s.inflictedko),0) AS inflictedko, IFNULL(SUM(s.inflictedinjuries),0) AS inflictedinjuries, IFNULL(SUM(s.inflicteddead),0) AS inflicteddead, IFNULL(SUM(s.inflictedtackles),0) AS inflictedtackles, IFNULL(SUM(s.inflictedmeterspassing),0) AS inflictedmeterspassing, IFNULL(SUM(s.inflictedmetersrunning),0) AS inflictedmetersrunning, IFNULL(SUM(s.sustainedinterceptions),0) AS sustainedinterceptions, IFNULL(SUM(s.sustainedcasualties),0) AS sustainedcasualties, IFNULL(SUM(s.sustainedstuns),0) AS sustainedstuns, IFNULL(SUM(s.sustainedko),0) AS sustainedko, IFNULL(SUM(s.sustainedinjuries),0) AS sustainedinjuries, IFNULL(SUM(s.sustainedtackles),0) AS sustainedtackles, sustaineddead FROM site_players AS p LEFT JOIN site_players_stats AS s ON s.player_id=p.id WHERE p.id=".$playerID." GROUP BY p.id";
      $resultStats = $con->query($sqlStats);
      $stats = $resultStats->fetch_object();
      return $stats;
};

//Save player's stats for a match
function player_stats_save($con, $player, $cyanideIDMatch){
    $matchBBBL = $con->query("SELECT id FROM site_matchs WHERE cyanide_id = '".$cyanideIDMatch."'")->fetch_row();
    $playerBBBL = $con->query("SELECT id FROM site_players WHERE cyanide_id = ".$player->id)->fetch_row();
    //Save players stats
    if($playerBBBL[0]){
        $sqlSaveStats = "INSERT INTO site_players_stats (player_id, cyanide_id_player, match_id,  cyanide_id_match,matchplayed, mvp, inflictedpasses, inflictedcatches, inflictedinterceptions, inflictedtouchdowns, inflictedcasualties, inflictedstuns, inflictedko, inflictedinjuries, inflicteddead, inflictedtackles, inflictedmeterspassing, inflictedmetersrunning, sustainedinterceptions, sustainedcasualties, sustainedstuns, sustainedko, sustainedinjuries, sustainedtackles, sustaineddead)
        VALUES (".$playerBBBL[0].",".$player->id.",".$matchBBBL[0].",'".$cyanideIDMatch."',".$player->matchplayed.",".$player->mvp.",".$player->stats->inflictedpasses.",".$player->stats->inflictedcatches.",".$player->stats->inflictedinterceptions.",".$player->stats->inflictedtouchdowns.",".$player->stats->inflictedcasualties.",".$player->stats->inflictedstuns.",".$player->stats->inflictedko.",".$player->stats->inflictedinjuries.",".$player->stats->inflicteddead.",".$player->stats->inflictedtackles.",".$player->stats->inflictedmeterspassing.",".$player->stats->inflictedmetersrunning.",".$player->stats->sustainedinterceptions.",".$player->stats->sustainedcasualties.",".$player->stats->sustainedstuns.",".$player->stats->sustainedko.",".$player->stats->sustainedinjuries.",".$player->stats->sustainedtackles.",".$player->stats->sustaineddead.")";
        $con->query($sqlSaveStats);
    };
};

?>
