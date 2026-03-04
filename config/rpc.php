<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Forrst\Http\Middleware\BootServer;
use Cline\Forrst\Http\Middleware\ForceJson;
use Cline\Forrst\Http\Middleware\RenderThrowable;
use Cline\Forrst\Protocols\ForrstProtocol;
use Illuminate\Routing\Middleware\SubstituteBindings;

return [
    /*
    |--------------------------------------------------------------------------
    | Forrst Protocol
    |--------------------------------------------------------------------------
    |
    | The protocol handles complete message format transformation between
    | internal representation and wire format. Forrst protocol uses a structured
    | request format with protocol version, id, and call object.
    |
    | Default: ForrstProtocol
    |
    */

    'protocol' => ForrstProtocol::class,

    /*
    |--------------------------------------------------------------------------
    | URN Vendor Identifier
    |--------------------------------------------------------------------------
    |
    | The vendor identifier used in URN generation for your functions. This
    | creates globally unique function identifiers following the format:
    | urn:<vendor>:forrst:fn:<function-name>
    |
    | Example: With vendor 'acme', a UserListFunction becomes:
    | urn:acme:forrst:fn:users:list
    |
    | The vendor should be a short, lowercase alphanumeric identifier that
    | represents your organization (e.g., 'acme', 'mycompany', 'stripe').
    |
    */

    'vendor' => env('FORRST_VENDOR', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Request Size
    |--------------------------------------------------------------------------
    |
    | The maximum size in bytes for incoming request payloads. Requests larger
    | than this limit will be rejected with a 413 status code to prevent
    | resource exhaustion attacks. Set to 0 to disable size validation.
    |
    | Default: 1048576 (1 MB)
    |
    */

    'max_request_size' => env('FORRST_MAX_REQUEST_SIZE', 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Forrst Function Namespaces
    |--------------------------------------------------------------------------
    |
    | Here you may define the namespaces that will be used to automatically
    | discover and load your Forrst function handlers. The framework will
    | scan these namespaces to register available functions for your servers.
    |
    */

    'namespaces' => [
        /*
        |--------------------------------------------------------------------------
        | Functions Namespace
        |--------------------------------------------------------------------------
        |
        | This namespace points to where your Forrst function handlers are located.
        | All classes within this namespace will be scanned and registered as
        | available Forrst functions if they implement the required interface.
        |
        */

        'functions' => 'App\\Http\\Functions',
    ],
    /*
    |--------------------------------------------------------------------------
    | Forrst Application Paths
    |--------------------------------------------------------------------------
    |
    | These paths are used by the package to locate various components of
    | your Forrst implementation. You may customize these paths based on
    | your application's directory structure and organizational preferences.
    |
    */

    'paths' => [
        /*
        |--------------------------------------------------------------------------
        | Functions Directory
        |--------------------------------------------------------------------------
        |
        | The filesystem path to the directory containing your function handlers.
        | This should correspond to the namespace defined above and is used for
        | file discovery and auto-registration of your Forrst function classes.
        |
        */

        'functions' => app_path('Http/Functions'),
    ],
    /*
    |--------------------------------------------------------------------------
    | Forrst Resources
    |--------------------------------------------------------------------------
    |
    | Resources provide a transformation layer between your Eloquent models
    | and the Forrst responses that are returned to your consumers. This
    | allows you to easily format and structure your response data. You may
    | register custom resource classes here that will be used throughout
    | your Forrst function handlers to transform models and collections.
    |
    */

    'resources' => [
        // 'users' => \App\Http\Resources\UserResource::class,
        // 'posts' => \App\Http\Resources\PostResource::class,
    ],
    /*
    |--------------------------------------------------------------------------
    | Forrst Server Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure one or more Forrst servers for your application.
    | Each server can have its own unique configuration including the path,
    | middleware stack, exposed functions, and API versioning. This allows you
    | to create separate Forrst endpoints for different parts of your application
    | or to version your API by running multiple servers simultaneously.
    |
    */

    'servers' => [
        [
            /*
            |--------------------------------------------------------------------------
            | Server Name
            |--------------------------------------------------------------------------
            |
            | The human-readable name for this Forrst server. This will be displayed
            | in the OpenRPC specification document and helps identify the server
            | when multiple Forrst endpoints are configured. Defaults to your app name.
            |
            */

            'name' => env('APP_NAME'),
            /*
            |--------------------------------------------------------------------------
            | Server Path
            |--------------------------------------------------------------------------
            |
            | The URI path where this Forrst server will accept requests. All Forrst
            | function calls should be sent as POST requests to this endpoint. You may
            | change this to any path that fits your application's URL structure.
            |
            */

            'path' => '/rpc',
            /*
            |--------------------------------------------------------------------------
            | Server Route Name
            |--------------------------------------------------------------------------
            |
            | The named route identifier for this server. This allows you to generate
            | URLs to the Forrst endpoint using Laravel's route helper functions. Ensure
            | this value is unique across all your configured Forrst servers.
            |
            */

            'route' => 'rpc',
            /*
            |--------------------------------------------------------------------------
            | API Version
            |--------------------------------------------------------------------------
            |
            | The semantic version number of this Forrst server's API. This is included
            | in the OpenRPC specification and helps clients understand which version
            | of your API they are interacting with. Follow semantic versioning rules.
            |
            */

            'version' => '1.0.0',
            /*
            |--------------------------------------------------------------------------
            | Middleware Stack
            |--------------------------------------------------------------------------
            |
            | Here you may specify the middleware that should be assigned to this
            | Forrst server. The middleware will be executed in the order listed here.
            | You may include both global middleware and route-specific middleware.
            |
            | Recommended middleware:
            | - RenderThrowable: Automatically converts exceptions to Forrst errors
            | - ForceJson: Ensures proper JSON content negotiation
            | - BootServer: Initializes the Forrst server context
            |
            */

            'middleware' => [
                RenderThrowable::class,
                SubstituteBindings::class,
                'auth:sanctum',
                ForceJson::class,
                BootServer::class,
            ],
            /*
            |--------------------------------------------------------------------------
            | Exposed Functions
            |--------------------------------------------------------------------------
            |
            | Control which Forrst functions are exposed through this server. Set this to
            | null to automatically expose all discovered functions, or provide an array
            | of function names to explicitly define which functions should be available.
            | This is useful for creating different API surfaces for different servers.
            |
            | Example: ['users.list', 'users.create', 'posts.*']
            |
            */

            'functions' => null,
        ],
    ],
];

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// Here endeth thy configuration, noble developer!                            //
// Beyond: code so wretched, even wyrms learned the scribing arts.            //
// Forsooth, they but penned "// TODO: remedy ere long"                       //
// Three realms have fallen since...                                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
//                                                  .~))>>                    //
//                                                 .~)>>                      //
//                                               .~))))>>>                    //
//                                             .~))>>             ___         //
//                                           .~))>>)))>>      .-~))>>         //
//                                         .~)))))>>       .-~))>>)>          //
//                                       .~)))>>))))>>  .-~)>>)>              //
//                   )                 .~))>>))))>>  .-~)))))>>)>             //
//                ( )@@*)             //)>))))))  .-~))))>>)>                 //
//              ).@(@@               //))>>))) .-~))>>)))))>>)>               //
//            (( @.@).              //))))) .-~)>>)))))>>)>                   //
//          ))  )@@*.@@ )          //)>))) //))))))>>))))>>)>                 //
//       ((  ((@@@.@@             |/))))) //)))))>>)))>>)>                    //
//      )) @@*. )@@ )   (\_(\-\b  |))>)) //)))>>)))))))>>)>                   //
//    (( @@@(.@(@ .    _/`-`  ~|b |>))) //)>>)))))))>>)>                      //
//     )* @@@ )@*     (@)  (@) /\b|))) //))))))>>))))>>                       //
//   (( @. )@( @ .   _/  /    /  \b)) //))>>)))))>>>_._                       //
//    )@@ (@@*)@@.  (6///6)- / ^  \b)//))))))>>)))>>   ~~-.                   //
// ( @jgs@@. @@@.*@_ VvvvvV//  ^  \b/)>>))))>>      _.     `bb                //
//  ((@@ @@@*.(@@ . - | o |' \ (  ^   \b)))>>        .'       b`,             //
//   ((@@).*@@ )@ )   \^^^/  ((   ^  ~)_        \  /           b `,           //
//     (@@. (@@ ).     `-'   (((   ^    `\ \ \ \ \|             b  `.         //
//       (*.@*              / ((((        \| | |  \       .       b `.        //
//                         / / (((((  \    \ /  _.-~\     Y,      b  ;        //
//                        / / / (((((( \    \.-~   _.`" _.-~`,    b  ;        //
//                       /   /   `(((((()    )    (((((~      `,  b  ;        //
//                     _/  _/      `"""/   /'                  ; b   ;        //
//                 _.-~_.-~           /  /'                _.'~bb _.'         //
//               ((((~~              / /'              _.'~bb.--~             //
//                                  ((((          __.-~bb.-~                  //
//                                              .'  b .~~                     //
//                                              :bb ,'                        //
//                                              ~~~~                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
