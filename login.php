<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/common.php';
require __DIR__ . '/user.php';

// Set up a user session
$user = new User();
$user->start_session();

$provider_name = isset($_GET['provider']) ? $_GET['provider'] : '';
if (empty($provider_name))
{
    echo 'No provider specified';
    exit;
}

$provider = NULL;
$provider_id = PROVIDER_NULL;
if ($provider_name == 'github')
{
    $provider = new \League\OAuth2\Client\Provider\Github($provider_configs['github']);
    $provider_id = PROVIDER_GITHUB;
    $scopes = ['user:email'];
}
else if ($provider_name == 'gitlab')
{
    $provider = new \Omines\OAuth2\Client\Provider\Gitlab($provider_configs['gitlab']);
    $provider_id = PROVIDER_GITLAB;
    $scopes = ['read_user'];
}
else if ($provider_name == 'google')
{
    $provider = new \League\OAuth2\Client\Provider\Google($provider_configs['google']);
    $provider_id = PROVIDER_GOOGLE;
    $scopes = ['email'];
}
else
{
    echo 'Invalid provider specified';
    exit;
}

// If we don't have an authorization code then get one
if (!isset($_GET['code']) && !isset($_GET['error']))
{
    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $options = [
        'scope' => $scopes
    ];

    $authorizationUrl = $provider->getAuthorizationUrl($options);

    // Get the state generated for you and store it to the session.
    $_SESSION['oauth2state'] = $provider->getState();

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;
}
// See if the user denied the request
else if (isset($_GET['error']))
{
    header('Location: ' . $script_path);
    exit;
}
// Check given state against previously stored one to mitigate CSRF attack
else if (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) 
{
    if (isset($_SESSION['oauth2state']))
    {
        unset($_SESSION['oauth2state']);
    }
    exit('Invalid state');
}
else
{
    try
    {
        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // Using the access token, we may look up details about the resource owner.
        $resourceOwner = $provider->getResourceOwner($accessToken);
        $provider_details = $resourceOwner->toArray();

        if ($provider_id == PROVIDER_GITHUB)
        {
            $request = $provider->getAuthenticatedRequest(
                'GET',
                'https://api.github.com/user/emails',
                $accessToken
            );
            $emails = (array) $provider->getParsedResponse( $request );
            foreach ( $emails as $email ) {
                if ( $email['primary'] ) {
                        $email = $email['email'];
                        break;
                }
            }

            $provider_user_id   = $provider_details['id'];
            $provider_email     = isset($email) ? $email : $provider_details['email'];
            $provider_username  = $provider_details['login'];
        }
        else if ($provider_id == PROVIDER_GITLAB)
        {
            $provider_user_id   = $provider_details['id'];
            $provider_email     = $provider_details['email'];
            $provider_username  = $provider_details['username'];
        }
        else if ($provider_id == PROVIDER_GOOGLE)
        {
            $provider_user_id   = $provider_details['sub'];
            $provider_email     = $provider_details['email'];
            $provider_username  = $provider_details['email'];
        }

        // See if we're already signed in and linking an additional provider
        if ($user->user_id != GUEST_USER)
        {
            // TODO: Handle users trying to link multiple of the same provider
            if (!$user->add_provider($provider_id, $provider_user_id))
            {
                exit;
            }
        }
        else // Guest user, make them an account
        {
            // First, see if a user is already linked to this provider account and is just signing back in
            $sql = 'SELECT user_id FROM ' . LINKS_TABLE . ' WHERE provider_id = :provider_id AND external_user_id = :provider_userid';
            $sth = $db->prepare($sql);
            $sth->execute(array(':provider_id' => $provider_id, ':provider_userid' => $provider_user_id));
            $result = $sth->fetch(PDO::FETCH_ASSOC);
            if ($result !== false)
            {
                $user->session_create($result['user_id'], $provider_id);
                header('Location: ' . $script_path);
                exit;
            }

            // See if a user with this email already exists and link the account
            $sql = 'SELECT id FROM ' . USER_TABLE . ' WHERE email = :email';
            $sth = $db->prepare($sql);
            $sth->execute(array(':email' => $provider_email));
            $result = $sth->fetch(PDO::FETCH_ASSOC);
            if ($result !== false)
            {
                $user->session_create($result['id'], PROVIDER_NULL);
                if (!$user->add_provider($provider_id, $provider_user_id))
                {
                    exit;
                }

                $user->set_provider($provider_id);
                header('Location: ' . $script_path);
                exit;
            }


            // No user exists, create one and link the account
            $sql = 'INSERT INTO ' . USER_TABLE . ' (email, username, subscription_email, subscriber_token) VALUES (:email, :username, :sub_email, :sub_token)';
            $sth = $db->prepare($sql);
            $result = $sth->execute(array(
                ':email'        => $provider_email,
                ':username'     => $provider_username,
                ':sub_email'    => $provider_email,
                ':sub_token'    => uuid_create(UUID_TYPE_RANDOM),
            ));
            if (!$result)
            {
                print_r($sth->errorInfo());
                exit;
            }

            $user_id = $db->lastInsertId();
            $user->session_create($user_id, PROVIDER_NULL);
            if (!$user->add_provider($provider_id, $provider_user_id))
            {
                exit;
            }
            $user->set_provider($provider_id);
        }

        header('Location: ' . $script_path);
        exit;

    } 
    catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) 
    {
        // Failed to get the access token or user details.
        exit($e->getMessage());
    }
}
