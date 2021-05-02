<?php

namespace SilasJoisten\Sonata\MultiUploadBundle\Controller;

use SilasJoisten\Sonata\MultiUploadBundle\Form\MultiUploadType;
use Sonata\Doctrine\Model\ManagerInterface;
use Sonata\MediaBundle\Controller\MediaAdminController;
use Sonata\MediaBundle\Model\MediaInterface;
use Sonata\MediaBundle\Provider\MediaProviderInterface;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MultiUploadController extends MediaAdminController
{
    /**
     * @var ManagerInterface
     */
    private $mediaManager;
    /**
     * @var ServiceLocator
     */
    private $providerLocator;

    private $paramRedirectTo;

    private $paramMaxUploadFileSize;

    public function __construct(
        ManagerInterface $mediaManager,
        Pool $sonataMediaPool,
        ServiceLocator $providerLocator,
        int $maxUploadFilesize,
        ?string $redirectTo
    ) {
        parent::__construct($sonataMediaPool);
        $this->mediaManager = $mediaManager;
        $this->providerLocator = $providerLocator;
        $this->paramRedirectTo = $redirectTo;
        $this->paramMaxUploadFileSize = $maxUploadFilesize;
    }

    public function createAction(?Request $request = null): Response
    {
        $this->admin->checkAccess('create');

        if (!$request->get('provider') && $request->isMethod('get')) {
            $pool = $this->getPool();

            return $this->render('@SonataMultiUpload/select_provider.html.twig', [
                'base_template' => $this->getBaseTemplate(),
                'admin' => $this->admin,
                'providers' => $pool->getProvidersByContext(
                    $request->get('context', $pool->getDefaultContext())
                ),
                'action' => 'create',
            ]);
        }

        return parent::createAction($request);
    }

    public function multiUploadAction(Request $request): Response
    {
        $this->admin->checkAccess('create');

        $providerName = $request->query->get('provider');
        $context = $request->query->get('context', 'default');

        /** @var MediaProviderInterface $provider */
        $provider = $this->providerLocator->get($providerName);

        $form = $this->createMultiUploadForm($provider, $context);
        if (!$request->files->has('file')) {
            return $this->render('@SonataMultiUpload/multi_upload.html.twig', [
                'action' => 'multi_upload',
                'base_template' => $this->getBaseTemplate(),
                'admin' => $this->admin,
                'form' => $form->createView(),
                'provider' => $provider,
                'maxUploadFilesize' => $this->paramMaxUploadFileSize,
                'redirectTo' => $this->paramRedirectTo,
            ]);
        }

        /** @var MediaInterface $media */
        $media = $this->mediaManager->create();
        $media->setContext($context);
        $media->setBinaryContent($request->files->get('file'));
        $media->setProviderName($providerName);
        $this->mediaManager->save($media);

        return new JsonResponse([
            'status' => 'ok',
            'path' => $provider->generatePublicUrl($media, MediaProviderInterface::FORMAT_ADMIN),
            'edit' => $this->admin->generateUrl('edit', ['id' => $media->getId()]),
            'id' => $media->getId(),
        ]);
    }

    private function createMultiUploadForm(MediaProviderInterface $provider, string $context): FormInterface
    {
        return $this->createForm(MultiUploadType::class, null, [
            'data_class' => $this->mediaManager->getClass(),
            'action' => $this->admin->generateUrl('multi_upload', ['provider' => $provider->getName()]),
            'provider' => $provider->getName(),
            'context' => $context,
        ]);
    }
}
