<?php
namespace Module\TenderBin\Actions;

use Module\HttpFoundation\Events\Listener\ListenerDispatch;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\OAuth2Client\Interfaces\iAccessToken;


class SearchBinAction
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
     * Search and Filter Stored BinData
     *
     * @param iAccessToken $token
     *
     *
     * Query Term:
     *
     * [
     *   'meta' => [
     *     'is_file' => [
     *       '$eq' => [
     *         true,
     *       ]
     *     ],
     *     'file_size' => [
     *       '$gt' => [
     *         40000,
     *       ]
     *     ],
     *   ],
     *   'version_tag' => [
     *     '$eq' => [
     *       'latest',
     *       'low_quality',
     *     ],
     *   ],
     * ];
     *
     * @return array
     */
    function __invoke($token = null)
    {
        # Parse Request Query params
        $q      = ParseRequestData::_($this->request)->parseQueryParams();
        // offset is Mongo ObjectID "58e107fa6c6b7a00136318e3"
        $offset = (isset($q['offset'])) ? $q['offset']       : null;
        $limit  = (isset($q['limit']))  ? (int) $q['limit']  : 30;


        # Build Expression Query Term

        // Search Bins Belong To This Owner Determine By Token
        $q['owner_identifier'] = $this->buildOwnerObjectFromToken($token);
        $expression = \Module\MongoDriver\parseExpressionFromArray( $q
            , ['meta', 'mime_type', 'version', 'owner_identifier']
            , 'allow' );

        $bins = $this->repoBins->find(
            $expression
            , $offset
            , (int) $limit + 1
        );


        $bins = \Poirot\Std\cast($bins)->toArray();

        // Check whether to display fetch more link in response?
        $linkMore = null;
        if (count($bins) > $limit) {
            array_pop($bins);                     // skip augmented content to determine has more?
            $nextOffset = $bins[count($bins)-1]; // retrieve the next from this offset (less than this)
            $linkMore   = \Module\HttpFoundation\Module::url(null);
            $linkMore   = (string) $linkMore->uri()->withQuery('offset='.($nextOffset['bindata']['uid']).'&limit='.$limit);
        }

        # Build Response

        $items = [];
        /** @var iBindata $bin */
        foreach ($bins as $bin) {
            $items[] = \Module\TenderBin\toResponseArrayFromBinEntity($bin) + [
                '_link' => (string) \Module\HttpFoundation\Module::url(
                    'main/tenderbin/resource/'
                    , array('resource_hash' => (string) $bin->getIdentifier())
                ),
            ];
        }


        # Build Response:

        $r = [
            'count' => count($items),
            'items' => $items,
            '_link_more' => $linkMore,
            '_self' => [
                'offset' => $offset,
                'limit'  => $limit,
            ],
        ];

        return [
            ListenerDispatch::RESULT_DISPATCH => $r,
        ];
    }
}
