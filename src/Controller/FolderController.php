<?php

// src/Controller/FolderController.php

namespace App\Controller;

use App\Entity\Folder;
use App\Form\DocumentType;
use App\Entity\Document;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class FolderController extends AbstractController
{

    #[Route('/folders', name: 'folder_index', methods: ['GET'])]
    public function index(FolderRepository $folderRepository): Response
    {
        $folders = $folderRepository->findBy(['owner' => $this->getUser()]);
        return $this->render('folder/index.html.twig', [
            'folders' => $folders
        ]);
    }

    #[Route('/folder/new', name: 'folder_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $folder = new Folder();
        $folder->setName($data['name']);
        $folder->setOwner($this->getUser());

        if (isset($data['parent_id']) && !empty($data['parent_id'])) {
            $parentFolder = $entityManager->getRepository(Folder::class)->find($data['parent_id']);
            if ($parentFolder) {
                $folder->setParent($parentFolder);
            }
        }

        $entityManager->persist($folder);
        $entityManager->flush();

        return new JsonResponse(['id' => $folder->getId(), 'name' => $folder->getName()]);
    }

    #[Route('/folder/{id}/edit', name: 'folder_edit', methods: ['POST'])]
    public function edit(Request $request, Folder $folder, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($folder->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $data = json_decode($request->getContent(), true);
        $folder->setName($data['name']);
        $entityManager->flush();

        return new JsonResponse(['id' => $folder->getId(), 'name' => $folder->getName()]);
    }

    #[Route('/folder/{id}/delete', name: 'folder_delete', methods: ['POST'])]
    public function delete(Request $request, Folder $folder, EntityManagerInterface $entityManager): JsonResponse
    {
    if ($folder->getOwner() !== $this->getUser()) {
        throw $this->createAccessDeniedException();
    }

    $force = $request->request->get('force', false);

    if (($folder->getDocuments()->count() > 0 || $folder->getSubFolders()->count() > 0) && !$force) {
        return new JsonResponse(['success' => false, 'message' => 'Folder not empty. Confirm deletion.'], 400);
    }

    if ($this->isCsrfTokenValid('delete' . $folder->getId(), $request->request->get('_token'))) {
        $this->deleteSubfolders($folder, $entityManager);

        $entityManager->remove($folder);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    return new JsonResponse(['success' => false], 400);
}

    private function deleteSubfolders(Folder $folder, EntityManagerInterface $entityManager): void
    {
        foreach ($folder->getSubfolders() as $subfolder) {
            $this->deleteSubfolders($subfolder, $entityManager);
            $entityManager->remove($subfolder);
        }
    }

    #[Route('/folders/move', name: 'get_folders_for_move', methods: ['GET'])]
    public function getFoldersForMove(FolderRepository $folderRepository): JsonResponse
    {
        $folders = $folderRepository->findBy(['owner' => $this->getUser()]);
        $data = [];

        foreach ($folders as $folder) {
            $data[] = [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/folder/{id}/move', name: 'folder_move', methods: ['POST'])]
    public function move(Request $request, Folder $folder, EntityManagerInterface $entityManager): JsonResponse
    {
        if ($folder->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $data = json_decode($request->getContent(), true);
        $targetFolderId = $data['folder'] ?? null;

        if (!$targetFolderId) {
            return new JsonResponse('No target folder ID provided', 400);
        }

        $targetFolder = $entityManager->getRepository(Folder::class)->find($targetFolderId);

        if (!$targetFolder) {
            return new JsonResponse('Target folder not found', 404);
        }

        if ($targetFolder->getOwner() !== $this->getUser()) {
            return new JsonResponse('Unauthorized access to target folder', 403);
        }

        if ($this->isSubfolder($folder, $targetFolder)) {
            return new JsonResponse('Cannot move a folder into one of its subfolders', 400);
        }

        $folder->setParent($targetFolder);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/folder/{id}', name: 'folder_view', methods: ['GET', 'POST'])]
    public function view(Folder $folder, FolderRepository $folderRepository, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $document = new Document();
        $document->setFolder($folder);
        $uploadForm = $this->createForm(DocumentType::class, $document);
        $uploadForm->handleRequest($request);

        if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
            $document->setOwner($this->getUser());
            $entityManager->persist($document);
            $entityManager->flush();

            return $this->redirectToRoute('folder_view', ['id' => $folder->getId()]);
        }

        $subfolders = $folderRepository->findBy(['owner' => $this->getUser(), 'parent' => $folder]);
        $allFolders = $folderRepository->findBy(['owner' => $this->getUser()]);

        return $this->render('folder/show.html.twig', [
            'folder' => $folder,
            'subfolders' => $subfolders,
            'folders' => $allFolders,
            'uploadForm' => $uploadForm->createView(),
        ]);
    }

    private function getFolderData($folders, CsrfTokenManagerInterface $csrfTokenManager): array
    {
        $data = [];
        foreach ($folders as $folder) {
            $subfolders = $folder->getSubfolders();
            $data[] = [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'csrf_token' => $csrfTokenManager->getToken('delete' . $folder->getId())->getValue(),
                'subfolders' => $this->getFolderData($subfolders, $csrfTokenManager)
            ];
        }
        return $data;
    }

    private function isSubfolder(Folder $folder, Folder $potentialParent): bool
    {
        $current = $potentialParent;

        while ($current !== null) {
            if ($current->getId() === $folder->getId()) {
                return true;
            }
            $current = $current->getParent();
        }

        return false;
    }
}
