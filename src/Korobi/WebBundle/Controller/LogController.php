<?php

namespace Korobi\WebBundle\Controller;

use Korobi\WebBundle\Document\Channel;
use Korobi\WebBundle\Document\Chat;
use Korobi\WebBundle\Document\Network;
use Korobi\WebBundle\Parser\IRCTextParser;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;

class LogController extends BaseController {

    public function homeAction() {

        $rawNetworks = $this->get('doctrine_mongodb')
            ->getManager()
            ->getRepository('KorobiWebBundle:Network')
            ->findNetworks()
            ->toArray();

        $networks = [];
        foreach ($rawNetworks as $network) {
            /** @var $network Network  */
            $name = $network->getSlug();
            if (!in_array($name, $networks)) {
                $networks[$name] = [
                    'name' => $network->getName(),
                    'href' => $this->generateUrl('logs_network', [
                        'network' => $name
                    ])
                ];
            }
        }

        return $this->render('KorobiWebBundle:controller/log:home.html.twig', [
            'networks' => $networks
        ]);
    }

    // TODO:
    // - invalid network name
    // - empty network
    public function networkAction($network) {
        $isAdmin = $this->authChecker->isGranted('ROLE_ADMIN');

        $rawChannels = $this->get('doctrine_mongodb')
            ->getManager()
            ->getRepository('KorobiWebBundle:Channel')
            ->findAllByNetwork($network)
            ->toArray();
        $channels = [];

        foreach ($rawChannels as $channel) {
            /** @var $channel Channel  */

            // only add channels with keys if we're an admin
            if ($channel->getKey() !== null && !$isAdmin) {
                continue;
            }

            $channels[] = [
                'name' => $channel->getChannel(),
                'href' => $this->generateUrl('logs_channel', [
                    'network' => $network,
                    'channel' => self::transformChannelName($channel->getChannel())
                ])
            ];
        }

        return $this->render('KorobiWebBundle:controller/log:network.html.twig', [
            'channels' => $channels
        ]);
    }

    public function channelAction(Request $request, $network, $channel, $year = false, $month = false, $day = false, $tail = false) {
        $logs = [];

        /** @var $dbChannel Channel */
        $dbChannel = $this->get('doctrine_mongodb')->getManager()->getRepository('KorobiWebBundle:Channel')->findByChannel($network, '#' . $channel)->toArray(false);
        if (empty($dbChannel)) {
            throw new Exception('Could not find channel');
        }

        // we exist, trim to first entry
        $dbChannel = $dbChannel[0];
        if ($dbChannel->getKey() !== null) {
            if ($request->query->get('key') !== $dbChannel->getKey()) {
                throw new Exception('Unauthorized');
            }
        }

        list($year, $month, $day, $tail) = self::populateRequest($year, $month, $day, $tail);

        $rawLogs = $this->get('doctrine_mongodb')
            ->getManager()
            ->getRepository('KorobiWebBundle:Chat')
            ->findAllByChannelAndDate(
                $network,
                '#' . $channel, // TODO
                new \MongoDate(strtotime(date('Y-m-d\TH:i:s.000\Z', mktime(0, 0, 0, $month, $day, $year)))),
                new \MongoDate(strtotime(date('Y-m-d\TH:i:s.000\Z', mktime(0, 0, 0, $month, $day + 1, $year))))
            )
            ->toArray();

        if ($tail !== false) {
            $rawLogs = array_slice($rawLogs, -$tail);
        }

        foreach ($rawLogs as $chat) {
            /** @var $chat Chat  */

            switch ($chat->getType()) {
                case 'ACTION':
                    $logs[] = $this->parseAction($chat);
                    break;
                case 'JOIN':
                    $logs[] = $this->parseJoin($chat);
                    break;
                case 'KICK':
                    $logs[] = $this->parseKick($chat);
                    break;
                case 'MESSAGE':
                    $logs[] = $this->parseMessage($chat);
                    break;
                case 'MODE':
                    $logs[] = $this->parseMode($chat);
                    break;
                case 'NICK':
                    $logs[] = $this->parseNick($chat);
                    break;
                case 'PART':
                    $logs[] = $this->parsePart($chat);
                    break;
                case 'QUIT':
                    $logs[] = $this->parseQuit($chat);
                    break;
                case 'TOPIC':
                    $logs[] = $this->parseTopic($chat);
                    break;
            }

        }

        return $this->render('KorobiWebBundle:controller/log:channel.html.twig', [
            'logs' => $logs
        ]);
    }

    // -----------------
    // ---- Parsing ----
    // -----------------

    private function parseAction(Chat $chat) {
        $result = '';

        /** @var $date \DateTime */
        $date = $chat->getDate();
        $result .= '[' . date('H:i:s', $date->getTimestamp()) . '] '; // time

        $result .= '* ';
        switch ($chat->getActorPrefix()) {
            case 'OWNER':
                $result .= '~';
                break;
            case 'ADMIN':
                $result .= '&';
                break;
            case 'OPERATOR':
                $result .= '@';
                break;
            case 'HALF_OP':
                $result .= '%';
                break;
            case 'VOICE':
                $result .= '+';
                break;
            case 'NORMAL':
                break;
        }
        $result .= self::transformActor($chat->getActorName());
        $result .= ' ';

        $result .= IRCTextParser::parse($chat->getMessage());

        return $result;
    }

    private function parseJoin(Chat $chat) {
        $result = '';

        /** @var $date \DateTime */
        $date = $chat->getDate();
        $result .= '[' . date('H:i:s', $date->getTimestamp()) . '] '; // time

        $result .= '<span class="irc--14-99">';
        $result .= '** ';
        switch ($chat->getActorPrefix()) {
            case 'OWNER':
                $result .= '~';
                break;
            case 'ADMIN':
                $result .= '&';
                break;
            case 'OPERATOR':
                $result .= '@';
                break;
            case 'HALF_OP':
                $result .= '%';
                break;
            case 'VOICE':
                $result .= '+';
                break;
            case 'NORMAL':
                break;
        }
        $result .= self::transformActor($chat->getActorName());
        $result .= ' joined the channel';
        $result .= '</span>';

        return $result;
    }

    private function parseKick(Chat $chat) {
        $result = '';

        /** @var $date \DateTime */
        $date = $chat->getDate();
        $result .= '[' . date('H:i:s', $date->getTimestamp()) . '] '; // time

        $result .= '** ';
        switch ($chat->getActorPrefix()) {
            case 'OWNER':
                $result .= '~';
                break;
            case 'ADMIN':
                $result .= '&';
                break;
            case 'OPERATOR':
                $result .= '@';
                break;
            case 'HALF_OP':
                $result .= '%';
                break;
            case 'VOICE':
                $result .= '+';
                break;
            case 'NORMAL':
                break;
        }
        $result .= self::transformActor($chat->getActorName());
        $result .= ' was kicked by ';
        $result .= $chat->getActorName();

        return $result;
    }

    private function parseMessage(Chat $chat) {
        $result = '';

        /** @var $date \DateTime */
        $date = $chat->getDate();
        $result .= '[' . date('H:i:s', $date->getTimestamp()) . '] '; // time

        $result .= '&lt;';
        switch ($chat->getActorPrefix()) {
            case 'OWNER':
                $result .= '~';
                break;
            case 'ADMIN':
                $result .= '&';
                break;
            case 'OPERATOR':
                $result .= '@';
                break;
            case 'HALF_OP':
                $result .= '%';
                break;
            case 'VOICE':
                $result .= '+';
                break;
            case 'NORMAL':
                break;
        }
        $result .= self::transformActor($chat->getActorName());
        $result .= '&gt; ';

        // message
        $result .= IRCTextParser::parse($chat->getMessage());

        return $result;
    }

    private function parseMode(Chat $chat) {
        $result = '';

        /** @var $date \DateTime */
        $date = $chat->getDate();
        $result .= '[' . date('H:i:s', $date->getTimestamp()) . '] '; // time

        $result .= '** ';
        switch ($chat->getActorPrefix()) {
            case 'OWNER':
                $result .= '~';
                break;
            case 'ADMIN':
                $result .= '&';
                break;
            case 'OPERATOR':
                $result .= '@';
                break;
            case 'HALF_OP':
                $result .= '%';
                break;
            case 'VOICE':
                $result .= '+';
                break;
            case 'NORMAL':
                break;
        }
        $result .= self::transformActor($chat->getActorName());
        $result .= ' sets mode ' . $chat->getMessage();

        return $result;
    }

    private function parseNick(Chat $chat) {
        $result = '';
        $prefix = '';

        /** @var $date \DateTime */
        $date = $chat->getDate();
        $result .= '[' . date('H:i:s', $date->getTimestamp()) . '] '; // time

        $result .= '** ';
        switch ($chat->getActorPrefix()) {
            case 'OWNER':
                $prefix .= '~';
                break;
            case 'ADMIN':
                $prefix .= '&';
                break;
            case 'OPERATOR':
                $prefix .= '@';
                break;
            case 'HALF_OP':
                $prefix .= '%';
                break;
            case 'VOICE':
                $prefix .= '+';
                break;
            case 'NORMAL':
                break;
        }
        $result .= $prefix;
        $result .= self::transformActor($chat->getActorName());
        $result .= ' is now known as ';
        $result .= $prefix;
        $result .= $chat->getRecipientName();

        return $result;
    }

    private function parsePart(Chat $chat) {
        $result = '';

        /** @var $date \DateTime */
        $date = $chat->getDate();
        $result .= '[' . date('H:i:s', $date->getTimestamp()) . '] '; // time

        $result .= '** ';
        switch ($chat->getActorPrefix()) {
            case 'OWNER':
                $result .= '~';
                break;
            case 'ADMIN':
                $result .= '&';
                break;
            case 'OPERATOR':
                $result .= '@';
                break;
            case 'HALF_OP':
                $result .= '%';
                break;
            case 'VOICE':
                $result .= '+';
                break;
            case 'NORMAL':
                break;
        }
        $result .= self::transformActor($chat->getActorName());
        $result .= ' left the channel';

        return $result;
    }

    private function parseQuit(Chat $chat) {
        $result = '';

        /** @var $date \DateTime */
        $date = $chat->getDate();
        $result .= '[' . date('H:i:s', $date->getTimestamp()) . '] '; // time

        $result .= '** ';
        switch ($chat->getActorPrefix()) {
            case 'OWNER':
                $result .= '~';
                break;
            case 'ADMIN':
                $result .= '&';
                break;
            case 'OPERATOR':
                $result .= '@';
                break;
            case 'HALF_OP':
                $result .= '%';
                break;
            case 'VOICE':
                $result .= '+';
                break;
            case 'NORMAL':
                break;
        }
        $result .= self::transformActor($chat->getActorName());
        $result .= ' ';

        $result .= 'has quit (' . $chat->getMessage() . ')';

        return $result;
    }

    private function parseTopic(Chat $chat) {
        $result = '';

        /** @var $date \DateTime */
        $date = $chat->getDate();
        $result .= '[' . date('H:i:s', $date->getTimestamp()) . '] '; // time

        $result .= '** ';
        switch ($chat->getActorPrefix()) {
            case 'OWNER':
                $result .= '~';
                break;
            case 'ADMIN':
                $result .= '&';
                break;
            case 'OPERATOR':
                $result .= '@';
                break;
            case 'HALF_OP':
                $result .= '%';
                break;
            case 'VOICE':
                $result .= '+';
                break;
            case 'NORMAL':
                break;
        }
        $result .= self::transformActor($chat->getActorName());
        $result .= ' ';

        $result .= 'has changed the topic to: ' . $chat->getMessage();

        return $result;
    }

    private static function populateRequest($year, $month, $day, $tail) {
        if (!$year) {
            $year = date('Y');
        }

        if (!$month) {
            $month = date('n');
        }

        if (!$day) {
            $day = date('d');
        }

        return [$year, $month, $day, $tail];
    }
}
