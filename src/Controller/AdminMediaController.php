<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class AdminMediaController extends AbstractController
{
    private const MAX_UPLOAD_BYTES = 3_000_000;

    /** @var array<string, string> */
    private const EXTENSIONS_BY_MIME_TYPE = [
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
        'image/webp' => 'webp',
    ];

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['gif', 'jpeg', 'jpg', 'png', 'svg', 'webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir
    ) {}

    #[Route('/api/admin/media', name: 'api_admin_media_upload', methods: ['POST'])]
    public function upload(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['message' => 'Aucun fichier reçu.'], 400);
        }

        if ($file->getSize() !== null && $file->getSize() > self::MAX_UPLOAD_BYTES) {
            return $this->json(['message' => 'Le fichier dépasse 3 Mo.'], 400);
        }

        $clientMimeType = (string) $file->getClientMimeType();
        $clientExtension = strtolower($file->getClientOriginalExtension());
        $extension = self::EXTENSIONS_BY_MIME_TYPE[$clientMimeType] ?? $clientExtension;

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->json(['message' => 'Format image non supporté.'], 400);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'media';
        $safeName = strtolower((string) $slugger->slug($originalName));
        $filename = sprintf('%s-%s.%s', $safeName, bin2hex(random_bytes(5)), $extension);
        $relativePath = '/uploads/media/'.$filename;
        $targetDirectory = $this->projectDir.'/public/uploads/media';

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            return $this->json(['message' => 'Impossible de préparer le dossier média.'], 500);
        }

        $file->move($targetDirectory, $filename);

        return $this->json([
            'path' => $relativePath,
            'url' => $request->getSchemeAndHttpHost().$relativePath,
        ]);
    }
}
