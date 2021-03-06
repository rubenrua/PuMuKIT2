<?php

namespace Pumukit\BasePlayerBundle\Twig;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\BasePlayerBundle\Services\TrackUrlService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BasePlayerExtension extends \Twig_Extension
{
    /**
     * @var RequestContext
     */
    protected $context;

    private $dm;
    private $trackService;

    public function __construct(DocumentManager $documentManager, RequestContext $context, TrackUrlService $trackService)
    {
        $this->dm = $documentManager;
        $this->context = $context;
        $this->trackService = $trackService;
    }

    /**
     * Get functions.
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('track_url', array($this, 'generateTrackFileUrl')),
            new \Twig_SimpleFunction('direct_track_url', array($this, 'generateDirectTrackFileUrl')),
        );
    }

    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('first_public_track', array($this, 'getFirstPublicTrackFilter')),
        );
    }

    /**
     * @param $track
     * @param int $reference_type
     *
     * @return string
     */
    public function generateTrackFileUrl($track, $reference_type = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->trackService->generateTrackFileUrl($track, $reference_type);
    }

    /**
     * @param $track
     * @param $request
     *
     * @return string
     *
     * @throws \Exception
     */
    public function generateDirectTrackFileUrl($track, $request)
    {
        return $this->trackService->generateDirectTrackFileUrl($track, $request);
    }

    /**
     * @param MultimediaObject $mmobj
     *
     * @return \Pumukit\SchemaBundle\Document\Track|null
     */
    public function getFirstPublicTrackFilter(MultimediaObject $mmobj)
    {
        return $mmobj->getDisplayTrack();
    }
}
