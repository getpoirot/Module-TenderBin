<?php
namespace Module\TenderBin
{
    use Module\TenderBin\Interfaces\Model\iBindata;


    /**
     * Build Prepared Array For Response
     *
     * @param iBindata $binData
     *
     * @return array
     */
    function toResponseArrayFromBinEntity(iBindata $binData)
    {
        if ($expiration = $binData->getDatetimeExpiration()) {
            $currDateTime   = new \DateTime();
            $currDateTime   = $currDateTime->getTimestamp();
            $expireDateTime = $expiration->getTimestamp();

            $expiration     = $expireDateTime - $currDateTime;
        }


        $r = [
            'bindata' => [
                'hash'           => (string) $binData->getIdentifier(),
                'title'          => $binData->getTitle(),
                'content_type'   => $binData->getMimeType(),
                'expiration'     => $expiration,
                'is_protected'   => $binData->isProtected(),

                'meta'           => \Poirot\Std\cast($binData->getMeta())->toArray(function($_, $k) {
                    return substr($k, 0, 2) == '__'; // filter specific options
                }),

                'version'      => [
                    'subversion_of' => ($v = $binData->getVersion()->getSubversionOf()) ? [
                        'bindata' => [
                            'uid' => ( $v ) ? (string) $v : null,
                        ],
                        '_link' => ( $v ) ? (string) \Module\HttpFoundation\Module::url(
                            'main/tenderbin/resource/'
                            , array('resource_hash' => (string) $v)
                        ) : null,
                    ] : null,
                    'tag' => $binData->getVersion()->getTag(),
                ],
            ],
        ];

        return $r;
    }
}
