<?php

namespace Korobi\WebBundle\Controller\Generic;

use Korobi\WebBundle\Controller\BaseController;
use Korobi\WebBundle\Document\Network;
use Korobi\WebBundle\Util\ExcludedHomepageChannels;

class HomeController extends BaseController {

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function homeAction() {
        $dbChannels = $this->get('doctrine_mongodb')
            ->getManager()
            ->getRepository('KorobiWebBundle:Channel')
            ->getRecentlyActiveChannels(5)
            ->toArray(false);
        $dbNetworks = $this->get('doctrine_mongodb')
            ->getManager()
            ->getRepository('KorobiWebBundle:Network')
            ->findNetworks()
            ->toArray(false);


        $targetChannel = $dbChannels[0]->getChannel();
        $targetNetwork = $dbChannels[0]->getNetwork();
        $blacklist = $this->getHomepageBlacklist();

        if ($blacklist->isBlacklisted($targetNetwork, $targetChannel)) {
            // This is to ensure we have a more interesting log section on the homepage
            // E.g. The Esper-facing Spigot channel only has one user speaking and isn't colourful

            $targetChannel = $dbChannels[1]->getChannel();
            $targetNetwork = $dbChannels[1]->getNetwork();
        }

        $messages = $this->get('doctrine_mongodb')
            ->getManager()
            ->getRepository('KorobiWebBundle:Chat')
            ->findLastChatsByChannel($targetNetwork, $targetChannel, 50)
            ->toArray(false);
        $messages = array_reverse($messages);

        $networks = [];
        /** @var Network $dbNetwork */
        foreach($dbNetworks as $dbNetwork) {
            $networks[$dbNetwork->getSlug()] = $dbNetwork->getName();
        }

        return $this->render('KorobiWebBundle:controller/generic:home.html.twig', [
            'now' => time(),
            'channels' => $dbChannels,
            'networks' => $networks,
            'messages' => $this->getRenderManager()->renderLogs($messages, ["message"]),
        ]);
    }

    /**
     * @return ExcludedHomepageChannels
     */
    protected function getHomepageBlacklist() {
        /** @var ExcludedHomepageChannels $blacklist */
        $blacklist = $this->get('korobi.homepage_excluded_channels');
        return $blacklist;
    }


}
