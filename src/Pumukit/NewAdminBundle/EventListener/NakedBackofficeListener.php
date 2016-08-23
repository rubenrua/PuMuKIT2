<?php

namespace Pumukit\NewAdminBundle\EventListener;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\SecurityContext;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Services\PersonService;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\NewAdminBundle\Controller\NewAdminController;

class NakedBackofficeListener
{
    private $domain;
    private $background;
    private $color;

    public function __construct($domain, $background, $color = '#ED6D00')
    {
        $this->domain = $domain;
        $this->background = $background;
        $this->color = $color;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $req = $event->getRequest();

        if ($req->getHttpHost() == $this->domain) {
            $req->attributes->set('nakedbackoffice', true);
            $req->attributes->set('nakedbackoffice_color', $this->background);
            $req->attributes->set('nakedbackoffice_main_color', $this->color);
        }
    }
}