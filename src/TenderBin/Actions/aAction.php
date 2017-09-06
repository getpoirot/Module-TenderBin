<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Events\EventHeapOfTenderBin;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Model\Entity\Bindata\OwnerObject;
use Poirot\Application\Exception\exAccessDenied;
use Poirot\Events\Event\BuildEvent;
use Poirot\Events\Interfaces\iEventHeap;
use Poirot\Events\Interfaces\Respec\iEventProvider;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\OAuth2Client\Interfaces\iAccessToken;


/**
 * Default Events Setting Can Be Set As Merged Config
 *
 *
 * @method \Traversable XX($owner_identifier, $skip = null, $limit = null)
 */
abstract class aAction
    extends \Module\Foundation\Actions\aAction
    implements iEventProvider
{
    const CONF = 'events';

    /** @var iHttpRequest */
    protected $request;
    /** @var EventHeapOfTenderBin */
    protected $events;

    protected $tokenMustHaveOwner  = false;
    protected $tokenMustHaveScopes = array(

    );


    /**
     * aAction constructor.
     * @param iHttpRequest $httpRequest @IoC /HttpRequest
     */
    function __construct(iHttpRequest $httpRequest)
    {
        $this->request = $httpRequest;
    }


    /**
     * Get Events
     *
     * @return iEventHeap
     */
    function event()
    {
        if (! $this->events ) {
            // Build Events From Merged Config
            $conf   = $this->sapi()->config()->get( \Module\TenderBin\Module::CONF_KEY );
            $conf   = $conf[self::CONF];

            $events = new EventHeapOfTenderBin;
            $builds = new BuildEvent([ 'events' => $conf ]);
            $builds->build($events);

            $this->events = $events;
        }

        return $this->events;
    }


    // ...

    /**
     * Assert Token
     *
     * - Must Have Token
     * - Token Must Bind To Resource Owner If Required
     * - Token Must Match Required Scopes
     *
     * @param iAccessToken $token
     *
     * @throws exAccessDenied
     */
    protected function assertTokenByOwnerAndScope($token)
    {
        # Validate Access Token
        \Module\OAuth2Client\Assertion\validateAccessToken(
            $token
            , (object) ['mustHaveOwner' => $this->tokenMustHaveOwner, 'scopes' => $this->tokenMustHaveScopes ]
        );

    }

    /**
     * Assert BinData Access By Check Permission
     * note: determine current user from token
     *
     * @param iBindata     $binData
     * @param iAccessToken $token
     * @param boolean      $forceCheckPermission Force Check Permission On None Protected Resource
     *                                             when we want update the resource not protected but
     *                                             access must be checked.
     *
     * @throws exAccessDenied
     */
    protected function assertAccessPermissionOnBindata(
        iBindata $binData = null
        , $token
        , $forceCheckPermission = false
    ) {
        if (false === $forceCheckPermission)
            // Has Force To Check Owner Permission?
            if (!$binData->isProtected())
                // Bin Data is not protected; let it play ...
                return;


        if (! $token)
            // There is no token given ...
            throw new exAccessDenied('You have not access to this data.');


        # Check Federation Access
        #
        $scopes = $token->getScopes();
        if ( in_array('federation', $scopes) ) {
            // Federation Scope Has All Access!!
            return;
        }


        // Check Access By Owner Object

        $ownerObject = $this->buildOwnerObjectFromToken($token);

        # Check Owner Privilege On Modify Bindata
        if ($ownerObject instanceof OwnerObject) {
            /** @var OwnerObject $binOwnerObject */
            $binOwnerObject = $binData->getOwnerIdentifier();
            if (! (
                   $ownerObject->getRealm() == $binOwnerObject->getRealm()
                && $ownerObject->getUid()   == $binOwnerObject->getUid()
            ) )
                // Mismatch Owner!!
                throw new exAccessDenied('Owner Mismatch; You have not access to edit this data.');
        }

    }

    /**
     * Build OwnerObject From Token
     *
     * @param iAccessToken $token
     *
     * @return OwnerObject
     */
    protected function buildOwnerObjectFromToken($token = null)
    {
        if (!$token instanceof iAccessToken)
            return null;


        $clientIdentifier = $token->getClientIdentifier();

        // Check Client Identifier Aliases:
        $conf  = $this->sapi()->config()->get( \Module\TenderBin\Module::CONF_KEY );
        $conf  = $conf['aliases_client'];
        while(isset($conf[$clientIdentifier]))
            $clientIdentifier = $conf[$clientIdentifier];


        $r = new OwnerObject;
        $r->setRealm($clientIdentifier);
        $r->setUid($token->getOwnerIdentifier());
        return $r;
    }
}
