<?php

namespace AppBundle\Controller;

use AppBundle\Document\Order;
use CL\Slack\Model\Attachment;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SlashCommandController extends Controller
{
    /**
     * @Route ("/slash-command")
     * @param Request $request
     */
    public function slashCommandAction(Request $request)
    {
        $userId = $request->get("user_id");
        $text = $request->get("text");
        $responseText = '';

        switch ($text) {
            case 'arrived': {
                $responseText = 'Ok, I\'ve told everyone about that :)';
                $this->sendLunchArrivedAnnouncment();
                break;
            }
            case 'menu': {
                $responseText = 'Ok, I will send you menu for the whole week... huh';
                $this->sendMenuToUserMessage($userId);
                break;
            }
            case 'today':
            case 'monday':
            case 'tuesday':
            case 'wednesday':
            case 'thursday':
            case 'friday':
            case 'saturday':
            case 'sunday': {
                $responseText = 'Ok, I will send you menu for ' . $text . '...';
                $this->sendOrderForDay($userId, $text);
                break;
            }
        }

        return new JsonResponse([
            "text" => $responseText,
        ]);
    }

    private function sendLunchArrivedAnnouncment()
    {
        $messageService = $this->get("AppBundle\Service\MessageService");

        $userRepository = $this->container->get('doctrine_mongodb')->getManager()->getRepository('AppBundle:User');
        $users = $userRepository->findBySubscribed(true);

        foreach ($users as $user) {
            $attachments = [];
            $orderRepository = $this->container->get('doctrine_mongodb')->getManager()->getRepository('AppBundle:Order');
            $orders = $orderRepository->findByUserAndDay($user, strtolower(date('l')));
            foreach ($orders as $order) {

                $attachment = new Attachment();
                $attachment->setPreText('*Your order for today*');
                $attachment->setColor('#7CD197');
                $attachment->setText($order->getMeal()->getName());
                $attachments[] = $attachment;

            }

            if (count($attachments)) {
                $messageService->sendMessage($user->getChannelId(), '*Lunch is here!*', $attachments);
            }
        }
    }

    private function sendMenuToUserMessage($userId)
    {
        $messageService = $this->get("AppBundle\Service\MessageService");
        $dm = $this->get('doctrine_mongodb')->getManager();
        $user = $dm->getRepository('AppBundle:User')->findOneBy(['userId' => $userId]);
        if ($user) {
            $meals = $dm->getRepository('AppBundle:Meal')->findAll();
            $groupedMeals = [];
            foreach ($meals as $meal) {
                if (!isset($groupedMeals[$meal->getDay()])) {
                    $groupedMeals[$meal->getDay()] = [];
                }
                $groupedMeals[$meal->getDay()][] = $meal;
            }

            $attachments = [];
            foreach ($groupedMeals as $day => $mealsInGroup) {
                $attachment = new Attachment();
                $attachment->setTitle(ucfirst($day));
                $attachment->setPreText('pretext...');
                $attachment->setColor('#7CD197');
                foreach ($mealsInGroup as $meal) {
                    $textArr[] = $meal->getName();
                }
                $attachment->setText(implode("\n", $textArr));
                unset($textArr);
                $attachments[] = $attachment;
            }
            if (count($attachments)) {
                $messageService->sendMessage($user->getChannelId(), '*Menu*', $attachments);
            }
        }
    }

    /**
     * @param string $userId
     * @param string $day
     */
    private function sendOrderForDay($userId, $day)
    {
        if (strtolower($day) == 'today') {
            $day = strtolower(date('l'));
        }
        $messageService = $this->get("AppBundle\Service\MessageService");
        $dm = $this->get('doctrine_mongodb')->getManager();
        $user = $dm->getRepository('AppBundle:User')->findOneBy(['userId' => $userId]);
        if ($user) {
            /** @var  Order[] $orders */
            $orders = $dm->getRepository('AppBundle:Order')->findByUserAndDay($user, $day);
            $attachments = [];
            if (count($orders)) {
                foreach ($orders as $order) {
                    $attachment = new Attachment();
                    $attachment->setPreText('pretext...');
                    $attachment->setColor('#7CD197');
                    $attachment->setText($order->getMeal()->getName());
                    $attachments[] = $attachment;
                }
                $messageService->sendMessage($user->getChannelId(), '*Your order for ' . $day . '*', $attachments);
            } else {
                $messageService->sendMessage($user->getChannelId(), '*You don\'t have orders for ' . $day . '*',
                    $attachments);
            }
        }
    }
}