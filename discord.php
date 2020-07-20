<?php

function match_to_discord($color, $match){
    //echo json_encode($match);
    $season = $match->season == $match->competition_name? $match->season : $match->season." - ".$match->competition_name;
    //winner
    if( $match->score_1 > $match->score_2 ){
      $winner = [$match->team_1_name, $match->coach_1_name];
      $loser = [$match->team_2_name, $match->coach_2_name];
    }
    else {
      $winner = [$match->team_2_name, $match->coach_2_name];
      $loser = [$match->team_1_name, $match->coach_1_name];
    }
    $titles = [
        "Match nul entre ".$winner[1]." et ".$loser[1],
        $winner[1]." et ".$loser[1]." se neutralisent",
        "Statu quo dans la rencotre opposant ".$winner[1]." à ".$loser[1]
    ];
    $descriptions = [
        "Match accroché où aucune équipe n'arrivera à se détacher. Un résultat qui, au final, n'arrange personne au classement.",
        "Les forces en présences étaient équilibrées pour ce match qui ne voit pas de vainqueur émerger.",
        "Un des joueurs, dont nous préserverons l'identité, nous a déclaré vouloir revenir au niveau amateur pour retrouver un intérêt à ce sport.",
        "La vraie question après ce match : les deux équipes étaient elles aussi bonne l'une que l'autre ou aussi mauvaise l'une que l'autre."
    ];
    //Victoires
    $score = abs($match->score_1 - $match->score_2);
    if( $score != 0){
        $titles = [
            "Victoire de ".$winner[1]." face à ".$loser[1],
            $winner[1]." s'impose face à ".$loser[1],
            $winner[1]." emporte son duel face à ".$loser[1],
            $loser[1]." chute face à ".$winner[1]
        ];
        $descriptions = [
            "L'équipe de ".$winner[1]." aura assuré le minimum en l'emportant dans ce match. Un seul Touchdown d'écart aura suffit à s'imposer.",
            $loser[1]." laisse son adversaire repartir victorieux, pour un malheureux Touchdown. C'est la bonne opération pour ".$winner[1]."!",
            "Petite victoire de ".$winner[1].". Un Touchdown de plus que son adversaire c'est tout ce qu'il faut pour faire la différence."
        ];
    }
    if( $score > 1) {
        $titles = [
            "Nette victoire de ".$winner[1]." face à ".$loser[1],
            "Victoire facile de ".$winner[1]." face à ".$loser[1],
            $loser[1]." chute lourdement face à ".$winner[1]
        ];
        $descriptions = [
            "Aucune chance pour ".$loser[1]." de recoller au score. ".$winner[1]." remporte le match sereinement avec une marge confortable.",
            $winner[1]." a tout simplement maitrisé son sujet dans cet affrontement. La différence de touchdown montre bien que ".$loser[1]." n'a rien pu faire aujourd'hui."
        ];
    };
    if( $score > 2) {
        $titles = [
          "Victoire écrasante de ".$winner[1]." sur ".$loser[1],
          $loser[1]." subit face à ".$winner[1],
          "Score fleuve dans la rencotre opposant ".$winner[1]." à ".$loser[1]
        ];
        $descriptions = [
            "Ce n'était plus un match c'était Abysse version longue. ".$loser[1]." n'a rien pu faire pour faire pour stopper son adversaire. Un score fleuve qui donne la victoire à ".$winner[1]
        ];
    };


    $shuffle = shuffle($titles);
    $title = $titles[$shuffle];
    $shuffle = shuffle($descriptions);
    $description = $descriptions[$shuffle];
    $url = $_ENV['WEBSITE_URL']."/#/match/".$match->id;
    $link = "\n[Toutes les informations sur notre site](".$url.")";
    $description = $season."\n **".$match->team_1_name." ".$match->team_1_score." - ".$match->team_2_score." ".$match->team_2_name."** \n\n ".$description.$link;                   ;
    post_message('#FFCC00', $title, $description, $url);
};

function post_message($color, $title, $description, $url){

    // Message Formatting -- https://discordapp.com/developers/docs/reference#message-formatting

    $timestamp = date("c", strtotime("now"));

    $json_data = json_encode([
        "avatar_url" => $_ENV['DISCORD_AVATAR'],
        "username" => $_ENV['DISCORD_USERNAME'],
        // Message
        //"content" => " pour Tribunes, le magazine de la BBBL",
        // Embeds Array
        "embeds" => [
            [
                // Embed left border color in HEX
                "color" => hexdec( $color ),
                // Embed Title
                "title" => $title,
                // Embed Type
                "type" => "rich",
                // Embed Description
                "description" => $description,
                // URL of title link
                "url" => $url,
                // Timestamp of embed must be formatted as ISO8601
                "timestamp" => $timestamp,
                // Footer
                "footer" => [
                    "text" => "Tribunes - Premier sur le BloodBowl",
                    "icon_url" => $_ENV['DISCORD_LOGO']
                ],
                "thumbnail" => [
                    "url" => $_ENV['DISCORD_LOGO']
                ]
            ]
        ]

    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

    $webhook = $_ENV['DISCORD_WEBHOOK'];
    $sendMessage = curl_init( $webhook );
    curl_setopt( $sendMessage, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt( $sendMessage, CURLOPT_POST, 1);
    curl_setopt( $sendMessage, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt( $sendMessage, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt( $sendMessage, CURLOPT_HEADER, 0);
    curl_setopt( $sendMessage, CURLOPT_RETURNTRANSFER, 1);

    $pof = curl_exec( $sendMessage );

    echo $pof;

    curl_close( $sendMessage );

}
?>
