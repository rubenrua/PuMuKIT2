<?php

namespace Pumukit\NewAdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Pumukit\NewAdminBundle\Form\Type\MultimediaObjectTemplateMetaType;

/**
 * @Security("is_granted('ROLE_ACCESS_MULTIMEDIA_SERIES')")
 */
class MultimediaObjectTemplateController extends MultimediaObjectController implements NewAdminControllerInterface
{
    /**
     * Display the form for editing or update the resource.
     *
     * @param Request $request
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @throws \Exception
     */
    public function updatemetaAction(Request $request)
    {
        $factoryService = $this->get('pumukitschema.factory');
        $personService = $this->get('pumukitschema.person');
        $groupService = $this->get('pumukitschema.group');

        $personalScopeRoleCode = $personService->getPersonalScopeRoleCode();
        $allGroups = $groupService->findAll();

        $roles = $personService->getRoles();
        if (null === $roles) {
            throw new \Exception('Not found any role.');
        }

        $sessionId = $this->get('session')->get('admin/series/id', null);
        $series = $factoryService->findSeriesById($request->get('seriesId'), $sessionId);

        if (null === $series) {
            throw new \Exception('Series with id '.$request->get('seriesId').' or with session id '.$sessionId.' not found.');
        }
        $this->get('session')->set('admin/series/id', $series->getId());

        $parentTags = $factoryService->getParentTags();
        $mmTemplate = $factoryService->getMultimediaObjectPrototype($series);

        $translator = $this->get('translator');
        $locale = $request->getLocale();
        $formMeta = $this->createForm(MultimediaObjectTemplateMetaType::class, $mmTemplate, array('translator' => $translator, 'locale' => $locale));

        $pubDecisionsTags = $factoryService->getTagsByCod('PUBDECISIONS', true);

        $method = $request->getMethod();
        if (in_array($method, array('POST', 'PUT', 'PATCH')) &&
            $formMeta->handleRequest($request)->isValid()) {
            $this->update($mmTemplate);

            return new JsonResponse(array('mmtemplate' => 'updatemeta'));
        }

        return $this->render('PumukitNewAdminBundle:MultimediaObjectTemplate:edit.html.twig',
            array(
                'mm' => $mmTemplate,
                'form_meta' => $formMeta->createView(),
                'series' => $series,
                'roles' => $roles,
                'personal_scope_role_code' => $personalScopeRoleCode,
                'pub_decisions' => $pubDecisionsTags,
                'parent_tags' => $parentTags,
                'groups' => $allGroups,
            )
        );
    }
}
