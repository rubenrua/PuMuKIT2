<?php

namespace Pumukit\LiveBundle\Controller;

use Pumukit\LiveBundle\Document\Live;
use Pumukit\LiveBundle\Document\Message;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/chat")
 */
class ChatController extends Controller
{
    /**
     * Show chat.
     *
     * @ParamConverter("multimediaObject", class="PumukitSchemaBundle:MultimediaObject", options={"id" = "id"})
     * @Route("/show/{id}", name="pumukit_live_chat_show")
     * @Template
     *
     * @param MultimediaObject $multimediaObject
     * @param Request          $request
     *
     * @return array
     */
    public function showAction(MultimediaObject $multimediaObject, Request $request)
    {
        $username = $this->getUser();
        if (!$username) {
            $sessionCookie = $request->cookies->get('PHPSESSID');
            $dm = $this->get('doctrine_mongodb.odm.document_manager');
            $messageRepo = $dm->getRepository(Message::class);
            $message = $messageRepo->findOneBy(array('cookie' => $sessionCookie));
            if ($message && ($author = $message->getAuthor())) {
                $username = $author;
            }
        }

        return array(
            'enable_chat' => $this->container->getParameter('pumukit_live.chat.enable'),
            'chatUpdateInterval' => $this->container->getParameter('pumukit_live.chat.update_interval'),
            'multimediaObject' => $multimediaObject,
            'username' => $username,
        );
    }

    /**
     * @ParamConverter("live", class="PumukitLiveBundle:Live", options={"id" = "id"})
     * @Route("/basic/show/{id}", name="pumukit_live_chat_basic_show")
     * @Template("PumukitLiveBundle:Chat:basicLiveShow.html.twig")
     *
     * @param Request $request
     * @param Live    $live
     *
     * @return array
     */
    public function showBasicAction(Request $request, Live $live)
    {
        $username = $this->getUser();
        if (!$username) {
            $sessionCookie = $request->cookies->get('PHPSESSID');
            $dm = $this->get('doctrine_mongodb.odm.document_manager');
            $messageRepo = $dm->getRepository(Message::class);
            $message = $messageRepo->findOneBy(array('cookie' => $sessionCookie));
            if ($message && ($author = $message->getAuthor())) {
                $username = $author;
            }
        }

        return array(
            'enable_chat' => $this->container->getParameter('pumukit_live.chat.enable'),
            'chatUpdateInterval' => $this->container->getParameter('pumukit_live.chat.update_interval'),
            'live' => $live,
            'username' => $username,
        );
    }

    /**
     * Post message.
     *
     * @ParamConverter("multimediaObject", class="PumukitSchemaBundle:MultimediaObject", options={"id" = "id"})
     * @Route("/post/{id}", name="pumukit_live_chat_post")
     *
     * @param MultimediaObject $multimediaObject
     * @param Request          $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function postAction(MultimediaObject $multimediaObject, Request $request)
    {
        $message = new Message();
        $message->setAuthor($request->get('name'));
        $message->setMultimediaObject($multimediaObject);
        $message->setMessage($request->get('message'));
        $message->setInsertDate(new \DateTime());
        $sessionCookie = $request->cookies->get('PHPSESSID');
        $message->setCookie($sessionCookie);

        try {
            $dm = $this->get('doctrine_mongodb.odm.document_manager');
            $dm->persist($message);
            $dm->flush();
        } catch (\Exception $e) {
            return new JsonResponse(array('message' => 'Error'), 500);
        }

        return new JsonResponse(array('message' => 'Successful'));
    }

    /**
     * @ParamConverter("live", class="PumukitLiveBundle:Live", options={"id" = "id"})
     * @Route("/basic/post/{id}", name="pumukit_live_chat_basic_post")
     *
     * @param Live    $live
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function postBasicAction(Live $live, Request $request)
    {
        $message = new Message();
        $message->setAuthor($request->get('name'));
        $message->setChannel($live->getId());
        $message->setMessage($request->get('message'));
        $message->setInsertDate(new \DateTime());
        $sessionCookie = $request->cookies->get('PHPSESSID');
        $message->setCookie($sessionCookie);

        try {
            $dm = $this->get('doctrine_mongodb.odm.document_manager');
            $dm->persist($message);
            $dm->flush();
        } catch (\Exception $e) {
            return new JsonResponse(array('message' => 'Error'), 500);
        }

        return new JsonResponse(array('message' => 'Successful'));
    }

    /**
     * List messages.
     *
     * @ParamConverter("multimediaObject", class="PumukitSchemaBundle:MultimediaObject", options={"id" = "id"})
     * @Route("/list/{id}", name="pumukit_live_chat_list")
     * @Template
     *
     * @param MultimediaObject $multimediaObject
     *
     * @return array
     */
    public function listAction(MultimediaObject $multimediaObject)
    {
        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $repo = $dm->getRepository(Message::class);
        $messages = $repo->findBy(
            array('multimediaObject' => $multimediaObject->getId()),
            array('insertDate' => 'asc')
        );

        return array(
            'messages' => $messages,
        );
    }

    /**
     * @ParamConverter("live", class="PumukitLiveBundle:Live", options={"id" = "id"})
     * @Route("/basic/list/{id}", name="pumukit_live_chat_basic_list")
     * @Template("PumukitLiveBundle:Chat:list.html.twig")
     *
     * @param Live $live
     *
     * @return array
     */
    public function listBasicAction(Live $live)
    {
        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $repo = $dm->getRepository(Message::class);
        $messages = $repo->findBy(
            array('channel' => $live->getId()),
            array('insertDate' => 'asc')
        );

        return array(
            'messages' => $messages,
        );
    }
}
