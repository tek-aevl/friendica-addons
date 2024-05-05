<?php
/*
 * Name: Discord OAuth2 SSO
 * Description: replace login and registration with Discord OAuth2 authentication.
 * Version: 1.0
 * Author: Ryan <https://friendica.verya.pe/profile/ryan>
 */

use Friendica\Core\Hook;
use Friendica\Core\Logger;
use Friendica\Core\Renderer;
use Friendica\Database\DBA;
use Friendica\DI;
use Friendica\Model\User;

require_once(__DIR__ . '/vendor/autoload.php');

define('PW_LEN', 32); // number of characters to use for random passwords

function discord_module() {}

function discord_init()
{
    if (DI::args()->getArgc() < 2) {
        return;
    }

    if (!discord_is_configured()) {
        echo 'Please configure the Discord add-on via the admin interface.';
        return;
    }

    switch (DI::args()->get(1)) {
        case 'authorize':
            discord_authorize();
            break;
        case 'callback':
            discord_callback();
            break;
    }
    exit();
}

function discord_authorize()
{
    $discordAuthUrl = DI::config()->get('discord', 'auth_url');
    $clientId = DI::config()->get('discord', 'client_id');
    $redirectUri = DI::baseUrl() . '/discord/callback';
    $scope = 'identify'; // You can adjust the scope as needed

    $authUrl = "$discordAuthUrl?client_id=$clientId&redirect_uri=$redirectUri&response_type=code&scope=$scope";
    
    header('Location: ' . $authUrl);
    exit();
}

function discord_callback()
{
    $code = $_GET['code'];

    $tokenEndpoint = DI::config()->get('discord', 'token_url');
    $clientId = DI::config()->get('discord', 'client_id');
    $clientSecret = DI::config()->get('discord', 'client_secret');
    $redirectUri = DI::baseUrl() . '/discord/callback';

    // Exchange authorization code for access token
    $data = [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'code' => $code,
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($tokenEndpoint, false, $context);
    $tokenData = json_decode($response, true);

    if (isset($tokenData['access_token'])) {
        $user = discord_get_user_info($tokenData['access_token']);

        if (!empty($user)) {
            discord_process_user($user);
        } else {
            echo 'Failed to fetch user information from Discord.';
        }
    } else {
        echo 'Failed to obtain access token from Discord.';
    }
}

function discord_get_user_info($accessToken)
{
    $userInfoEndpoint = DI::config()->get('discord', 'user_info_url');
    $headers = [
        'Authorization: Bearer ' . $accessToken,
    ];

    $options = [
        'http' => [
            'header' => implode("\r\n", $headers),
        ],
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($userInfoEndpoint, false, $context);

    return json_decode($response, true);
}

function discord_process_user($user)
{
    // Process user data here and create or retrieve the user's account
    // For example, you can use $user['id'] as the unique identifier for the user

    // Sample code to create a new user
    $username = $user['username'];
    $email = $username . '@discord.example.com'; // Adjust as needed
    $name = $user['username'];

    if (!DBA::exists('user', ['nickname' => $username])) {
        $newUser = discord_create_user($username, $email, $name);
    } else {
        $newUser = User::getByNickname($username);
    }

    if (!empty($newUser['uid'])) {
        DI::auth()->setForUser(DI::app(), $newUser);
    }

    // Redirect the user to the desired page after successful authentication
    header('Location: ' . DI::baseUrl());
    exit();
}

function discord_create_user($username, $email, $name)
{
    // Create a new user account
    // You can adjust this function to suit your user creation process
}

function discord_is_configured()
{
    return
        DI::config()->get('discord', 'client_id') &&
        DI::config()->get('discord', 'client_secret') &&
        DI::config()->get('discord', 'auth_url') &&
        DI::config()->get('discord', 'token_url') &&
        DI::config()->get('discord', 'user_info_url');
}

function discord_addon_admin(string &$o)
{
    // Admin configuration interface goes here
}

function discord_addon_admin_post()
{
    // Handle saving of Discord OAuth2 settings
}

discord_init();
?>
