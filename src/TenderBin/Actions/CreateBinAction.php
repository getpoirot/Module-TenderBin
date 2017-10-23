<?php
namespace Module\TenderBin\Actions;

use Module\HttpFoundation\Events\Listener\ListenerDispatch;
use Module\TenderBin\Events\DataCollector;
use Module\TenderBin\Events\EventHeapOfTenderBin;
use Module\TenderBin\Model\Entity;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\OAuth2Client\Interfaces\iAccessToken;
use Poirot\Std\Exceptions\exUnexpectedValue;


class CreateBinAction
    extends aAction
{
    /** @var iRepoBindata */
    protected $repoBins;


    /**
     * ValidatePage constructor.
     *
     * @param iHttpRequest $httpRequest @IoC /HttpRequest
     * @param iRepoBindata $repoBins    @IoC /module/tenderbin/services/repository/Bindata
     */
    function __construct(iHttpRequest $httpRequest, iRepoBindata $repoBins)
    {
        parent::__construct($httpRequest);

        $this->repoBins = $repoBins;
    }


    /**
     * Create New Bin and Persist
     *
     * @param string                  $custom_uid
     * @param iAccessToken|null $token
     *
     * @return array
     * @throws \Exception
     */
    function __invoke($custom_uid = null, $token = null)
    {
        ## Assert Token
        #
        $this->assertTokenByOwnerAndScope($token);


        ## Create Post Entity From Http Request
        #
        $hydrateBindata = new Entity\BindataHydrate(
            Entity\BindataHydrate::parseWith($this->request) );

        try
        {
            $entityBindata  = new Entity\BindataEntity($hydrateBindata);
            // Determine Owner Identifier From Token
            $entityBindata->setOwnerIdentifier( $this->buildOwnerObjectFromToken($token) );
            // Set Custom Object Identifier if it given.
            $entityBindata->setIdentifier($custom_uid);


            $validatorConfig = $this->sapi()->config()
                ->get(\Module\TenderBin\Module::CONF_KEY);

            __(new Entity\BindataValidate($entityBindata, $validatorConfig['validator']))
                ->assertValidate();

        } catch (exUnexpectedValue $e)
        {
            // TODO Handle Validation ...
            throw new exUnexpectedValue('Validation Failed', null,  400, $e);
        }


        # Persist Data
        #

        // Event
        //
        $e = $this->event()
            ->trigger(EventHeapOfTenderBin::BEFORE_CREATE_BIN, [
                /** @see DataCollector */
                'binObject' => $entityBindata
            ]);


        $pBinEntity = $this->repoBins->insert($entityBindata);


        // Event
        //
        $e->trigger(EventHeapOfTenderBin::AFTER_BIN_CREATED, [
            /** @see DataCollector */
            'binObject' => clone $pBinEntity
        ]);


        # Build Response
        #
        $linkParams = [
            'resource_hash' => $pBinEntity->getIdentifier(), ];

        if ( $pBinEntity->getMeta()->has('is_file') )
            $linkParams += [
                'filename' => $pBinEntity->getMeta()->get('filename'), ];

        $result = \Module\TenderBin\toResponseArrayFromBinEntity($pBinEntity)
            + [
                '_link' => (string) \Module\HttpFoundation\Actions::url(
                    'main/tenderbin/resource/'
                    , $linkParams
                ),
            ];


        return [
            ListenerDispatch::RESULT_DISPATCH => $result,
        ];
    }

}
