<?php

// src/Controller/FolderController.php

namespace App\Controller;

use App\Form\FolderType;
use App\Form\DocumentType;
use App\Entity\Folder;
use App\Entity\Document;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;


class FolderController extends AbstractController
{
    #[Route('/dashboard', name: 'folder_index', methods: ['GET', 'POST'])]
    public function index(Request $request, FolderRepository $folderRepository, EntityManagerInterface $entityManager): Response
    {
        // Créer un nouveau dossier et le formulaire associé
        $folder = new Folder();
        $form = $this->createForm(FolderType::class, $folder);

        $documents = new Document();
        $formDocuments = $this->createForm(DocumentType::class, $documents);

        // Gérer la requête pour le formulaire
        $form->handleRequest($request);
        $formDocuments->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $folder->setOwner($this->getUser());
            $entityManager->persist($folder);
            $entityManager->flush();

            $this->addFlash('success', 'Dossier créé avec succès.');

            return $this->redirectToRoute('folder_index');
        }

        if ($formDocuments->isSubmitted() && $formDocuments->isValid()) {
            $documents->setOwner($this->getUser()); // Set the owner for the Document entity
            $entityManager->persist($documents);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier uploadé avec succès.');

            // Check if the folder entity has been persisted and has an ID
            if ($folder->getId()) {
                return $this->redirectToRoute('folder_show', ['id' => $folder->getId()]);
            }
            return $this->redirectToRoute('folder_index');
        }

        // Récupérer les dossiers principaux (ceux qui n'ont pas de parent)
        $folders = $folderRepository->findBy([
            'owner' => $this->getUser(),
            'parent' => null // Filtrer pour ne récupérer que les dossiers principaux
        ]);

        // Récupérer les documents
        $documents = $entityManager->getRepository(Document::class)->findBy(['folder' => null]);



        // Rendre la vue avec les dossiers et le formulaire
        return $this->render('folder/index.html.twig', [
            'folders' => $folders,
            'documents' => $documents,
            'form' => $form->createView(),
            'formDocuments' => $formDocuments->createView(),
        ]);
    }




    // fonction pour editer un dossier OK
    #[Route('/folder/{id}/edit', name: 'folder_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Folder $folder, EntityManagerInterface $entityManager): Response
    {
       // $this->denyAccessUnlessGranted('EDIT', $folder);

        $form = $this->createForm(FolderType::class, $folder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le dossier a été modifié avec succès.');

            return $this->redirectToRoute('folder_index');
        }

        if ($form->isEmpty()) {
            $this->addFlash('error', 'Le dossier n\'a pas pu être modifié.');
        }

        return $this->render('folder/edit.html.twig', [
            'form' => $form->createView(),
            'folder' => $folder,
        ]);
    }

    // fonction pour supprimer un dossier OK
    #[Route('/folder/{id}/delete', name: 'folder_delete', methods: ['POST'])]
    public function delete(Request $request, Folder $folder, EntityManagerInterface $entityManager): RedirectResponse
    {
        //$this->denyAccessUnlessGranted('DELETE', $folder);


        if ($this->isCsrfTokenValid('delete'.$folder->getId(), $request->request->get('_token'))) {
            $entityManager->remove($folder);
            $entityManager->flush();

            $this->addFlash('success', 'Le dossier a été supprimé avec succès.');
        }

        return $this->redirectToRoute('folder_index');
    }

    #[Route('/folder/{id}', name: 'folder_show', methods: ['GET', 'POST'])]
    public function show(Folder $folder, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur connecté est le propriétaire du dossier
        if ($folder->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit d\'accéder à ce dossier.');
        }

        // Récupérer uniquement les dossiers principaux de l'utilisateur pour le menu à gauche
        $user = $this->getUser();
        $folders = $entityManager->getRepository(Folder::class)->findBy([
            'owner' => $user,
            'parent' => null // Filtrer pour ne récupérer que les dossiers principaux
        ]);

        // Récupérer les sous-dossiers du dossier courant
        $subfolders = $entityManager->getRepository(Folder::class)->findBy(['parent' => $folder]);
        $documents = $entityManager->getRepository(Document::class)->findBy(['folder' => $folder]);
        // Créer le formulaire pour ajouter un nouveau sous-dossier dans le dossier actuel
        $newFolder = new Folder();
        $form = $this->createForm(FolderType::class, $newFolder, [
            'parent' => $folder, // Passer le dossier courant comme parent
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newFolder->setOwner($this->getUser());
            $newFolder->setParent($folder);  // Définir le dossier courant comme parent
            $entityManager->persist($newFolder);
            $entityManager->flush();

            $this->addFlash('success', 'Sous-dossier créé avec succès.');

            return $this->redirectToRoute('folder_show', ['id' => $folder->getId()]);
        }



        $formDocumentsViewOrRedirect = $this->getFormUploadDocuments($request, $folder, $entityManager);

        // Si la méthode retourne une redirection, on la renvoie directement
        if ($formDocumentsViewOrRedirect instanceof RedirectResponse) {
            return $formDocumentsViewOrRedirect;
        }


        return $this->render('folder/show.html.twig', [
            'folder' => $folder,
            'folders' => $folders, // Dossiers principaux pour le menu à gauche
            'subfolders' => $subfolders, // Sous-dossiers pour le contenu principal
            'form' => $form->createView(), // Formulaire pour ajouter un sous-dossier
            'documents' => $documents, // Documents pour le contenu principal
            'formDocuments' => $formDocumentsViewOrRedirect, // Formulaire pour ajouter un document
        ]);
    }


    #[Route('/folder/{parentId}/subfolder/{id}', name: 'subfolder_show', methods: ['GET', 'POST'])]
    public function showSubfolder(Folder $folder, Request $request, EntityManagerInterface $entityManager, int $parentId): Response
    {
        // Vérifier que l'utilisateur connecté est le propriétaire du sous-dossier
        if ($folder->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit d\'accéder à ce dossier.');
        }

        // Récupérer uniquement les dossiers principaux de l'utilisateur pour le menu à gauche
        $user = $this->getUser();
        $folders = $entityManager->getRepository(Folder::class)->findBy([
            'owner' => $user,
            'parent' => null // Filtrer pour ne récupérer que les dossiers principaux
        ]);


        // Récupérer les sous-dossiers du sous-dossier courant
        $subfolders = $entityManager->getRepository(Folder::class)->findBy(['parent' => $folder]);

        // Créer le formulaire pour ajouter un nouveau sous-dossier dans le sous-dossier actuel
        $newFolder = new Folder();
        $form = $this->createForm(FolderType::class, $newFolder, [
            'parent' => $folder, // Passer le sous-dossier courant comme parent
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newFolder->setOwner($this->getUser());
            $newFolder->setParent($folder);  // Définir le sous-dossier courant comme parent
            $entityManager->persist($newFolder);
            $entityManager->flush();

            $this->addFlash('success', 'Sous-dossier créé avec succès.');

            return $this->redirectToRoute('subfolder_show', [
                'parentId' => $parentId,
                'id' => $folder->getId(),
            ]);
        }


        $formDocumentsViewOrRedirect = $this->getFormUploadDocuments($request, $folder, $entityManager);

        // Si la méthode retourne une redirection, on la renvoie directement
        if ($formDocumentsViewOrRedirect instanceof RedirectResponse) {
            return $formDocumentsViewOrRedirect;
        }

        return $this->render('folder/show_subfolder.html.twig', [
            'folder' => $folder,
            'folders' => $folders, // Dossiers principaux pour le menu à gauche
            'subfolders' => $subfolders, // Sous-dossiers pour le contenu principal
            'form' => $form->createView(), // Formulaire pour ajouter un sous-dossier
            'formDocuments' => $formDocumentsViewOrRedirect, // Formulaire pour ajouter un document

        ]);
    }

    public function getFormUploadDocuments(Request $request, Folder $folder, EntityManagerInterface $entityManager): \Symfony\Component\Form\FormView|RedirectResponse
    {
        $documents = new Document();
        $formDocuments = $this->createForm(DocumentType::class, $documents);

        $formDocuments->handleRequest($request);

        if ($formDocuments->isSubmitted() && $formDocuments->isValid()) {
            // Assigner l'utilisateur courant comme propriétaire du document
            $documents->setOwner($this->getUser());

            // Associer le document au dossier
            $documents->setFolder($folder);

            // Enregistrer l'entité Document dans la base de données
            $entityManager->persist($documents);
            $entityManager->flush();

            // Ajouter un message flash de succès
            $this->addFlash('success', 'Fichier uploadé avec succès.');

            // On verie que l'on et pas dans un sous-dossier dans  quel cas on retourne une redirection dans le sous-dossier en question
            if ($folder->getParent() !== null) {
                return $this->redirectToRoute('subfolder_show', [
                    'parentId' => $folder->getParent()->getId(),
                    'id' => $folder->getId(),
                ]);
            }
            // Retourner une redirection si le formulaire est soumis et valide
            return $this->redirectToRoute('folder_show', ['id' => $folder->getId()]);
        }

        // Retourner la vue du formulaire si le formulaire n'a pas été soumis ou est invalide
        return $formDocuments->createView();
    }









}
