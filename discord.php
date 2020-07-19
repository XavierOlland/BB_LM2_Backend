<?php

function match_to_discord($color){
  $title = "Gros match";
  $description = "Do we need mention <@!150553575477608448>";
  $url = $_ENV['WEBSITE_URL']."/#/match/";
  post_message($color, $title, $description, $url);
};

function post_message($color, $title, $description, $url){

  // Message Formatting -- https://discordapp.com/developers/docs/reference#message-formatting

  $timestamp = date("c", strtotime("now"));

  $json_data = json_encode([
    // Avatar URL.
    "avatar_url" => "https://bbbl.fr/img/avatars/106.png?size=512",
    // Username
    "username" => "Edwy Pleine-Aile pour Tribunes",
    // Message
    //"content" => " pour Tribunes, le magazine de la BBBL",
    // Embeds Array
    "embeds" => [
        [
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
            // Embed left border color in HEX
            "color" => hexdec( $color ),
            // Footer
            "footer" => [
                "text" => "Tribunes - Premier sur le BloodBowl",
                "icon_url" => "https://bbbl.fr/img/Logo_S.1ea53edf.png?size=375"
            ],
            // Image to send
            // "image" => [
            //     "url" => "https://ru.gravatar.com/userimage/28503754/1168e2bddca84fec2a63addb348c571d.jpg?size=600"
            // ],
            // Thumbnail
            "thumbnail" => [
                "url" => "https://bbbl.fr/img/Logo_S.1ea53edf.png?size=375"
            ],
            // Author
            // "author" => [
            //     "name" => "Edwy Pleine-Aile pour Tribunes",
            //     "url" => "https://bbbl.fr/"
            // ],
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

  curl_exec( $sendMessage );

  curl_close( $sendMessage );

}
?>
