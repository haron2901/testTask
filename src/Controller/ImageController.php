<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('image/index.html.twig');
    }

    #[Route('/fetch-images', name: 'fetch_images', methods: ['POST'])]
    public function fetchImages(Request $request): Response
    {
        $url = $request->request->get('url');
        if (empty($url)) {
            return $this->redirectToRoute('home');
        }

        $response = $this->client->request('GET', $url);
        $html = $response->getContent();

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $images = $dom->getElementsByTagName('img');

        $imageData = [];
        $totalSize = 0;

        $baseUrl = parse_url($url);
        $baseUrl = $baseUrl['scheme'] . '://' . $baseUrl['host'];

        foreach ($images as $image) {
            $src = $image->getAttribute('src');

            if (!filter_var($src, FILTER_VALIDATE_URL)) {
                $src = $baseUrl . '/' . ltrim($src, '/');
            }

            $imageResponse = $this->client->request('GET', $src, [
                'buffer' => true,
            ]);

            if ($imageResponse->getStatusCode() === 200) {
                $imageSize = $imageResponse->getHeaders()['content-length'][0] ?? 0;
                $totalSize += $imageSize;

                $imageData[] = [
                    'url' => $src,
                    'size' => $imageSize,
                ];
            }
        }

        $totalSizeMb = $totalSize / (1024 * 1024);

        return $this->render('image/results.html.twig', [
            'images' => $imageData,
            'totalCount' => count($imageData),
            'totalSizeMb' => $totalSizeMb,
        ]);
    }
}
