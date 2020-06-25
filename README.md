# BloodBowl League Manager 2
The aim of the League Manager is to deliver a tool for leagues playing BloodBowl 2 from Cyanide Studio to organize themselves and keep track of their competitions and results.  
The overall tool is a combination of 3 elements:  
- [A web interface built with VueJS](https://github.com/XavierOlland/BloodBowl_LeagueManager2): to display the competitions, results and statistics.
- [A backend built with PHP](https://github.com/XavierOlland/BB_LM2_Backend): to update the data and serve it to the interface.
- [A phpBB forum](https://www.phpbb.com/): for coach to organize them.

You can see the whole project running for french Blood Bowl Bastonm League at [https://bbbl.fr](https://bbbl.fr)

# This backend
Its role is to retrieve data from Cyanide Studio's servers via their API in order to store it in a mySQL database (because of phpBB) and then serve tit on the web interface of the League Manager.
It's built from scratch with no framework and is using mysqli, due to past versions of the project and limited time and knowledge from the author ;)  

## Dependencies
This project is using [phpdotenv](https://github.com/vlucas/phpdotenv) (and therefore [Composer](https://getcomposer.org/)) to manage .env files

## Cyanide API
In order to connect to Cyanide API server, you'll need a key from [them](http://www.cyanide-studio.com)

## Installation
1. Copy the .env.eample file as .env with your ingame league name and your Cyanide API key.
2. Run a `composer install`
3. Transfer everything to your webserver


# Disclaimer
This project has no link whatsoever with phpBB, Cyanide Studio, Focus Interactive or Games Workshop (though we'd like to thank Cyanide for their help and support with their API).
BloodBowl is a registered trademark of Games Workshop.
