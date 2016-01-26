<?php

namespace Pumukit\PaellaPlayerBundle\Controller;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\VideoEditorBundle\Document\Annotation;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class PaellaRepositoryController extends Controller
{
    /**
     * @Route("/paellarepository/{id}.{_format}", defaults={"_format"="json"}, requirements={"_format": "json|xml"})
     * @Method("GET")
     */
    public function indexAction(MultimediaObject $mmobj, Request $request)
    {
        //TODO: Do the annotation getting using a service function.
        //$opencastAnnotationService = $this->container->get('video_editor.opencast_annotations');
        $serializer = $this->get('serializer');
        $picService = $this->get('pumukitschema.pic');
        $pic = $picService->getFirstUrlPic($mmobj, true, false);

        $data = array();
        $data['streams'] = array();

        $tracks = $this->getMmobjTracks($mmobj);

        foreach( $tracks as $track) {
            $src = $this->getAbsoluteUrl($request, $track->getUrl());
            $mimeType = $track->getMimetype();
            $data['streams'][] = array('sources' => array('mp4' => array(array('src' => $src,
                                                                               'mimetype' => $mimeType,
                                                                               'res' => array('w' => 0, 'h' => 0)))),
                                       'preview' => $pic);
        }


        $data['metadata'] = array('title' => $mmobj->getTitle(),
                                  'description' => $mmobj->getDescription(),
                                  'duration' => 0);

        $response = $serializer->serialize($data, $request->getRequestFormat());
        return new Response($response);
    }

    /**
     * Returns the absolute url from a given path or url
     */
    private function getAbsoluteUrl($request, $url) {
        if (false !== strpos($url, '://') || 0 === strpos($url, '//')) {
            return $url;
        }

        if ('' === $host = $request->getHost()) {
            return $url;
        }
        return $request->getSchemeAndHttpHost().$request->getBasePath().$url;
    }

    /**
     * Returns an array (can be empty) of tracks for the mmobj
     */
    private function getMmobjTracks(MultimediaObject $mmobj)
    {
        $tracks = array();
        if($mmobj->getProperty('opencast')) {
            $presenterTracks = $mmobj->getFilteredTracksWithTags(array('presenter/delivery'));
            $presentationTracks = $mmobj->getFilteredTracksWithTags(array('presentation/delivery'));

            foreach($presenterTracks as $track) {
                if($track->getVcodec() == 'h264') {
                    $tracks[] = $track;
                    break;
                }
            }
            foreach($presentationTracks as $track) {
                if($track->getVcodec() == 'h264') {
                    $tracks[] = $track;
                    break;
                }
            }
            if(count($tracks) <= 0) {
                $track =  $mmobj->getFilteredTrackWithTags(array('sbs'));
                if($track)
                    $tracks[] = $track;
            }
        }
        else {
            $track = $mmobj->getFilteredTrackWithTags(array('display'));
            if($track)
                $tracks[] = $track;
        }

        return $tracks;
    }

}