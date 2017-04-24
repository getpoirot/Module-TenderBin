<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Module\TenderBin\Model\Entity\BindataEntity;
use Poirot\Http\Interfaces\iHttpRequest;


class CleanupBinsAction
    extends aAction
{
    /** @var iRepoBindata */
    protected $repoBins;


    /**
     * ValidatePage constructor.
     *
     * @param iHttpRequest $request  @IoC /
     * @param iRepoBindata $repoBins @IoC /module/tenderbin/services/repository/Bindata
     */
    function __construct(iHttpRequest $request, iRepoBindata $repoBins)
    {
        parent::__construct($request);

        $this->repoBins = $repoBins;
    }


    /**
     * Retrieve Bin Meta Info
     *
     * @return array
     * @throws \Exception
     */
    function __invoke()
    {
        set_time_limit(0);
        ignore_user_abort();

        # Retrieve Expired Data

        $expired  = $this->repoBins->findExpired();
        $i = 0; $total = 0;
        /** @var BindataEntity $bindataEntity */
        foreach ($expired as $bindataEntity) {
            $i++; $total ++;

            echo sprintf("Delete: (%s). \r\n", (string) $bindataEntity->getIdentifier());
            $this->repoBins->deleteOneByHash( $bindataEntity->getIdentifier() );

            if ($i == 20) {
                sleep(3);
                $i = 0;
            }
        }

        echo 'Complete Cleanup ... '. $total. 'Entity was Deleted.'. "\r\n";
        die;
    }
}
