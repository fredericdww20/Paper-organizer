<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Folder;
use App\Form\DocumentType;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\DirectoryNamer;
use Vich\UploaderBundle\Mapping\PropertyMapping;

#[Route('/document')]
class DocumentController extends AbstractController
{
    private $security;
    private $documentsDirectory;
    private $directoryNamer;

    public function __construct(Security $security,string $documentsDirectory, DirectoryNamer $directoryNamer)
    {
        $this->security = $security;
        $this->documentsDirectory = $documentsDirectory;
        $this->directoryNamer = $directoryNamer;
    }

    #[Route('/{id}/delete', name: 'document_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $entityManager): Response
    {
        if ($document->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $folderId = $document->getFolder()->getId();

        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $entityManager->remove($document);
            $entityManager->flush();

            return $this->redirectToRoute('folder_view', ['id' => $folderId]);
        }

        return new Response('Invalid CSRF token', 400);
    }

    #[Route('/{id}/move', name: 'document_move', methods: ['POST'])]
    public function move(Request $request, Document $document, EntityManagerInterface $entityManager): Response
    {
        $folderId = $request->request->get('folder');
        if (!$folderId) {
            return $this->redirectToRoute('folder_view', ['id' => $document->getFolder()->getId(), 'error' => 'No folder ID provided']);
        }

        $folder = $entityManager->getRepository(Folder::class)->find($folderId);

        if (!$folder) {
            return $this->redirectToRoute('folder_view', ['id' => $document->getFolder()->getId(), 'error' => 'Invalid folder ID']);
        }

        if ($folder->getOwner() !== $this->getUser()) {
            return $this->redirectToRoute('folder_view', ['id' => $document->getFolder()->getId(), 'error' => 'User does not own the target folder']);
        }

        $document->setFolder($folder);
        $entityManager->flush();

        return $this->redirectToRoute('folder_view', ['id' => $folder->getId()]);
    }


    #[Route('/download/{id}', name: 'document_download', methods: ['GET'])]
    public function download(Document $document, ParameterBagInterface $params): Response
    {
        $user = $document->getOwner();

        $filePath = $params->get('kernel.project_dir') . '/public/uploads/documents/' . $user->getUserIdentifier() . '/' . $document->getFileName();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('The file does not exist');
        }

        return $this->file($filePath, $document->getFileName(), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/upload/{folder}', name: 'document_upload', methods: ['POST'])]
    public function upload(Request $request, Folder $folder, EntityManagerInterface $entityManager, LoggerInterface $logger): JsonResponse
    {
        $document = new Document();

        // Assurez-vous que le propriétaire du dossier est défini
        if ($folder->getOwner() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $document->setFolder($folder);
            $document->setOwner($this->getUser()); // Assurez-vous que le propriétaire du document est défini
            $document->setUpdatedAt(new \DateTimeImmutable());
            $document->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($document);
            $entityManager->flush();

            $logger->info('Document persisted with ID: ' . $document->getId());
            $logger->info('File name: ' . $document->getFileName());

            return new JsonResponse(['success' => true, 'document' => [
                'id' => $document->getId(),
                'title' => $document->getTitle(),
                'fileName' => $document->getFileName(),
                'folder' => $folder->getId()
            ]]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $logger->error('Form is invalid.');
            $errors = [];
            foreach ($form->getErrors(true, false) as $error) {
                $errors[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'errors' => $errors], 400);
        }

        return new JsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
    }

    #[Route('/folders', name: 'get_folders', methods: ['GET'])]
    public function getFolders(FolderRepository $folderRepository): JsonResponse
    {
        $folders = $folderRepository->findBy(['owner' => $this->getUser()]);
        $data = $this->getFolderData($folders);

        return new JsonResponse($data);
    }

    private function getFolderData($folders): array
    {
        $data = [];
        foreach ($folders as $folder) {
            $data[] = [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'subfolders' => $this->getFolderData($folder->getSubfolders()->toArray())
            ];
        }
        return $data;
    }

    #[Route('/{id}/preview', name: 'document_preview', methods: ['GET'])]
    public function preview(Document $document): Response
    {
        $mapping = new PropertyMapping('file', 'file');

        $directoryName = $this->directoryNamer->directoryName($document, $mapping);

        $filePath = $this->documentsDirectory . '/' . $directoryName . '/' . $document->getFileName();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('The file does not exist');
        }

        $response = new Response();
        $response->setContent(file_get_contents($filePath));
        $response->headers->set('Content-Type', mime_content_type($filePath));

        return $response;
    }
}
