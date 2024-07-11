<?php

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Repository\FolderRepository;

class MenuBuilder
{
    private $factory;
    private $folderRepository;
    private $requestStack;

    public function __construct(FactoryInterface $factory, FolderRepository $folderRepository, RequestStack $requestStack)
    {
        $this->factory = $factory;
        $this->folderRepository = $folderRepository;
        $this->requestStack = $requestStack;
    }

    public function createBreadcrumbMenu(array $options): ItemInterface
    {
        $menu = $this->factory->createItem('root');
        $menu->addChild('Home', ['route' => 'folder_index']);

        $request = $this->requestStack->getCurrentRequest();
        $folderId = $request->attributes->get('id');

        if ($folderId) {
            $folder = $this->folderRepository->find($folderId);

            if ($folder) {
                $breadcrumbs = $this->getBreadcrumbs($folder);
                foreach ($breadcrumbs as $breadcrumb) {
                    $menu->addChild($breadcrumb['name'], [
                        'route' => 'folder_view', 
                        'routeParameters' => ['id' => $breadcrumb['id']]
                    ]);
                }
                $menu->addChild($folder->getName(), [
                    'route' => 'folder_view', 
                    'routeParameters' => ['id' => $folder->getId()]
                ]);
            }
        }

        return $menu;
    }

    private function getBreadcrumbs($folder)
    {
        $breadcrumbs = [];
        while ($folder) {
            $breadcrumbs[] = [
                'name' => $folder->getName(),
                'id' => $folder->getId()
            ];
            $folder = $folder->getParent();
        }
        return array_reverse($breadcrumbs);
    }
}
