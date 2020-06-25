<?php
function forum_profiles_update($con){
    $css = "";

    $sqlTeam = "SELECT c.user_id, t.cyanide_id AS team_id, t.name, t.logo, t.color_1, t.color_2, t.param_id_race, t.sponsor_id
      FROM site_teams AS t
      INNER JOIN site_coachs AS c ON c.cyanide_id=t.coach_id
      LEFT JOIN site_parameters AS p ON p.id=t.param_id_race
      LEFT JOIN site_competitions AS d ON d.id=(SELECT competition_id FROM site_matchs WHERE (team_id_1=t.id OR team_id_2=t.id) ORDER BY id DESC LIMIT 1)
      WHERE t.active=1";
    $result = $con->query($sqlTeam);
    while($team = $result->fetch_object()) {
        //Update forum database
        $sqlProfile = "UPDATE forum_profile_fields_data SET pf_race=".$team->param_id_race.", pf_equipe='<a href=\"http://bbbl.fr/#/team/".$team->team_id."\" target=\"_blank\">".mysqli_real_escape_string($con, $team->name)."</a>' WHERE user_id=".$team->user_id;

        $con->query($sqlProfile);

        /*
        $sqlRank = "UPDATE forum_users SET user_rank=(SELECT rank_id FROM forum_ranks WHERE rank_title='".$team->league_name."') WHERE user_id=".$team->user_id;
        $con->query($sqlRank);*/

    //Prepare css content
    $css .= ".casque-logo.coach".$team->user_id."{ background: url('/resources/logo/Logo_".$team->logo.".png') bottom right no-repeat; background-size:contain; }
    .color".$team->user_id."-1{ fill:".$team->color_1."; }
    .color".$team->user_id."-2{ fill:".$team->color_2."; }
    \n";
    }
  //Create css
  file_put_contents('../css/teams.css', $css);
  $json = new stdClass;
  $json->type = "success";
  $json->text = "Forum et CSS mis à jour.";
  echo json_encode($json);

}

function forum_fetch_all($con,$id){
  $forums = [];
  $sqlParents = "SELECT forum_id, forum_name FROM `forum_forums` WHERE parent_id=".$id." AND forum_id != 24 ORDER BY forum_id DESC";
  $resultParents = $con->query($sqlParents);
  while($parent = $resultParents->fetch_object()) {
    $subForums = [];
    $sqlSubs = "SELECT forum_id, forum_name FROM `forum_forums` WHERE parent_id=".$parent->forum_id." ORDER BY forum_id";
    $resultSubs = $con->query($sqlSubs);
    while($sub = $resultSubs->fetch_object()) {
      array_push($subForums, $sub);
    }
    $parent->subs = $subForums;
    array_push($forums, $parent);

  }
  echo json_encode($forums);
}

function forum_links_add($con, $competition){
  $current_id = $competition->forum_id;
  $maxRound = max(array_column($array, 'round'));
  for($i=1; $i<=$competition->rounds_count; $i++){
    $sqlLink = "INSERT INTO site_forum_links (competition_id, round, forum_id) VALUES (".$competition->id.",".$i.",".$current_id.")";
    $con->query($sqlLink);
    $current_id++;
  }
}

?>
