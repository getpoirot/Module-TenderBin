<?php
namespace Module\TenderBin\Actions;

use Module\Foundation\Actions\IOC;
use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Module\TenderBin\Model\BindataOwnerObject;
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\Interfaces\iHttpRequest;


class SearchBinAction
    extends aAction
{
    /** @var iRepoBindata */
    protected $repoBins;


    /**
     * ValidatePage constructor.
     *
     * @param iRepoBindata $repoBins @IoC /module/tenderbin/services/repository/Bindata
     */
    function __construct(iRepoBindata $repoBins)
    {
        $this->repoBins = $repoBins;
    }


    /**
     * Search and Filter Stored BinData
     *
     * @param array              $queryTerm   Query Search Term
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
    function __invoke($queryTerm = null, $offset = null, $limit = 30)
    {
        $bins = $this->repoBins->find($queryTerm, (int) $offset, (int) $limit);

        $items = [];
        /** @var iEntityBindata $bin */
        foreach ($bins as $bin) {
            $items[] = [
                '$bindata' => [
                    'uid'          => (string) $bin->getIdentifier(),
                    'title'        => $bin->getTitle(),
                    'mime_type'    => $bin->getMimeType(),
                    'is_protected' => $bin->isProtected(),

                    'meta'         => \Poirot\Std\cast($bin->getMeta())->toArray(function($_, $k) {
                        return substr($k, 0, 2) == '__'; // filter specific options
                    }),

                    'version'      => [
                        'subversion_of' => ($v = (string) $bin->getVersion()->getSubversionOf()) ? [
                            '$bindata' => [
                                'uid' => ( $v ) ? $v : null,
                            ],
                            '_link' => ( $v ) ? (string) IOC::url(
                                'main/tenderbin/resource/'
                                , array('resource_hash' => (string) $v)
                            ) : null,
                        ] : null,
                        'tag' => $bin->getVersion()->getTag(),
                    ],
                ],

                '_link' => (string) IOC::url(
                    'main/tenderbin/resource/'
                    , array('resource_hash' => (string) $bin->getIdentifier())
                ),
            ];
        }


        # Build Response:

        $r = [
            '_self' => [
                'offset' => $offset,
                'limit'  => $limit,
            ],
            'count' => count($items),
            'items' => $items,
        ];
        return [
            ListenerDispatch::RESULT_DISPATCH => $r,
        ];
    }


    // Action Chain Helpers:

    /**
     * Parse Query Terms From Http Request
     *
     * /search?meta=is_file:true|file_size>40000&mime_type=audio/mp3&version_tag=latest|low_quality
     *        &offset=latest_id&limit=20
     *
     * @return callable
     */
    static function functorParseQueryTermFromRequest()
    {
        /**
         * @param iHttpRequest       $request
         * @param BindataOwnerObject $ownerObject Search Bins Belong To This Owner Determine By Token
         *
         * @return array
         * @throws \Exception
         */
        return function ($request = null, BindataOwnerObject $ownerObject = null) {
            if (!$request instanceof iHttpRequest)
                throw new \RuntimeException('Cant attain Http Request Object.');


            # Parse Search Query Term
            $queryTerm = ParseRequestData::_($request)->parseQueryParams();
            
            // Search Bins Belong To This Owner Determine By Token
            $queryTerm['owner_identifier'] = $ownerObject;

            $parsed = [];
            foreach ($queryTerm as $field => $term)
            {
                if (!in_array($field, ['meta', 'mime_type', 'owner_identifier', 'version']) )
                    continue;

                // $field => latest_id
                // $field => is_file:true
                // $field => is_file:true|file_size>4000000
                // $field => \Traversable ---> field:value|other_field:value2

                if (is_string($term))
                {
                    if (false !== strpos($term, '|'))
                        // mime_type=audio/mp3|audio/wave
                        $termExchange = explode('|', $term);
                    else
                        // version=latest
                        $termExchange = [$term];

                    $term = [];
                    foreach ($termExchange as $i => $t)
                    {
                        // $t=is_file:true
                        if (preg_match('/(?P<operand>\w+)(?P<operator>[:<>])(?<value>\w+)/', $t, $matches)) {
                            switch ($matches['operator']) {
                                case ':': $operator = '$eq'; break;
                                case '>': $operator = '$gt'; break;
                                case '<': $operator = '$lt'; break;
                                default: throw new \Exception("Operator {$matches['operator']} is invalid.");
                            }

                            if (!isset($term[$matches['operand']]))
                                $term[$matches['operand']] = [];

                            if (in_array(strtolower($matches['value']), ['true', 'false']))
                                $matches['value'] = filter_var($matches['value'], FILTER_VALIDATE_BOOLEAN);

                            $term[$matches['operand']] = array_merge_recursive(
                                $term[$matches['operand']]
                                , [
                                    $operator => [
                                        $matches['value'],
                                    ],
                                ]
                            );
                        } else {
                            // $t=audio/mp3
                            if (in_array(strtolower($t), ['true', 'false']))
                                $t = filter_var($t, FILTER_VALIDATE_BOOLEAN);

                            $term = array_merge_recursive($term, [
                                '$eq' => [$t],
                            ]);
                        }
                    }
                }

                elseif ($term instanceof \Traversable) {
                    $iterTerm = $term; $term = [];
                    foreach ($iterTerm as $operand => $value)
                        $term[$operand] = [
                            '$eq' => [
                                $value
                            ],
                        ];
                }

                elseif (!is_array($term))
                    throw new \Exception(sprintf('Invalid Term (%s)', \Poirot\Std\flatten($term)));

                $parsed[$field] = $term;

            }// end foreach


            # Return to next chain as 'queryTerm' argument
            return [
                'queryTerm' => $parsed,
                'offset'    => (isset($queryTerm['offset'])) ? $queryTerm['offset'] : null,
                'limit'     => (isset($queryTerm['limit']))  ? $queryTerm['limit']  : null,
            ];
        };
    }
}
