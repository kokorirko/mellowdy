<?php

namespace App\Controller;

use App\Entity\MellowUser;
use App\Entity\NotionPage;
use App\Entity\User;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\SpotifyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;



class DefaultController extends AbstractController
{
    /**
     * @var SpotifyService
     */
    private $spotifyService;

    /**
     * @var HttpClientInterface
     */
    private $httpClient;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var UserService
     */
    private  $userService;


    public function __construct(SpotifyService $spotifyService,
    HttpClientInterface $httpClient,
    LoggerInterface $logger,
    ParameterBagInterface  $parameterBag,
    EntityManagerInterface $entityManager,
    UserService $userService)
    {
        $this->spotifyService = $spotifyService;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->parameterBag = $parameterBag;
        $this->entityManager = $entityManager;
        $this->userService = $userService;

    }

    /**
     * @Route("/", name="default")
     */
    public function index(Request $request): Response
    {
        $request->headers->set('Authorization', 'Bearer 6ec9c464487dd4569c3c00a8c7fab2fbb37c70b6');
        /** @var MellowUser $user */
        $user = $this->userService->getUserFromRequest($request);
        if (null === $user) {
            new Response('Unauthorized', 401);

        }
         $return = $this->spotifyService->getSpotifyAddItem($user, $user->getUserToken());
       return $this->json($return);


    }
    /**
     * @Route("/oauth", name="oauth")
     */
    public function oauth(): Response
    {
       $client_id = '47fcde357cd7454088ed5b0bf054c823';
       $location = 'http://127.0.0.1:8080/exchange_token';
       $scope = 'user-read-playback-state playlist-read-private playlist-read-collaborative playlist-modify-private playlist-modify-public';

       $oauth_string = sprintf(
           'https://accounts.spotify.com/authorize?client_id=%s&response_type=code&redirect_uri=%s&scope=%s',
           $client_id,
           $location,
           $scope
        );
        return $this->redirect($oauth_string);
    }

    /**
     * @Route("/exchange_token",name="exchange_token")
     */
    public function exchange_token(Request $request): Response{
        $authorization_code = $request->get('code');
        $client_secret = $this->parameterBag->get('client_secret');
        try {
            $basicAuth = base64_encode(sprintf('%s:%s',  '47fcde357cd7454088ed5b0bf054c823', $client_secret));
            $header = [
                'Authorization' => sprintf('Basic %s', $basicAuth),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
            $body = [
                'client_id' => '47fcde357cd7454088ed5b0bf054c823',
                'client_secret' => $client_secret,
                'code' => $authorization_code,
                'grant_type' => 'authorization_code',
                'redirect_uri' =>'http://127.0.0.1:8080/exchange_token',
            ];

            $response = $this->httpClient->request(
                'POST',
                'https://accounts.spotify.com/api/token',
                ['headers' => $header , 'body' => $body ]

            );
            $json_response = json_decode($response->getContent(), true);
        } catch (ClientException $e) {
            $this->logger->error(
                sprintf(
                    'Error : %s',
                    $e->getMessage()
                )
            );

            return $this->json($e->getMessage());
        }


        $user_token = $json_response['access_token'];
        $this->spotifyService->storeUser($user_token);
        //return $this->json($json_response);
        //return $this->redirect('localhost:3000?frontToken');
        // Redirect to front-end with front token as a query param
        // localhost:3000/?frontToken=xxxxx
       //return $this->redirect('/');
        //$frontToken = $this->entityManager->getRepository(MellowUser::class)->findOneByFrontToken('front_token');
        $frontToken = substr(sha1($user_token),0,64);
        return $this->redirect(sprintf('http://localhost:3000/?frontToken=%s',$frontToken));
        //return var_dump($frontToken);

    }

    /**
     * @Route("/savepages", name="savePages")
     */
    public function saveUser(Request $request): Response
    {
        $user = $this->userService->getUserFromRequest($request);
        if (null === $user) {
            return new Response('Unauthorized', 401);
        }
        $user->getToken();
        $this->spotifyService->getSpotifyMe($user->getToken());

        return $this->json('');
    }
    

    /**
     * @Route("/error", name="error")
     */
    public function error(): Response
    {
        return $this->json('nope.');
    }
}