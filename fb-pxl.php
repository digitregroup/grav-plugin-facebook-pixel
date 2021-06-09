<?php

namespace Grav\Plugin;

use \Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

class FbPxlPlugin extends Plugin
{
    public function onPluginsInitialized(): void
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
            'onFormProcessed' => ['onFormProcessed', 0]
        ]);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPageInitialized' => ['onPageInitialized', 0],
            'onFormProcessed' => ['onFormProcessed', 0]
        ];
    }

    /**
     * @param string $raw
     * @return array
     */
    private function getRule(string $raw): array
    {
        $ruleset = explode('|', $raw);

        $ruleset[0] = str_replace('*', '.*', $ruleset[0]);
        $ruleset[0] = str_replace('/', '\/', $ruleset[0]);

        if (count($ruleset) == 1) {
            $ruleset[1] = 'page';
        }

        return $ruleset;
    }

    /**
     * @param string $reg
     * @param string $url
     * @return boolean
     */
    private function regMatches(string $reg, string $url): bool
    {
        return (preg_match("/$reg/i", $url) == 1);
    }

    /**
     * @param array $rules
     * @param string $url
     * @param string $type
     * @return string
     */
    private function getEventName(array $rules, string $url, string $type = 'page'): string
    {
        $eventName = 'PageView';

        foreach ($rules as $raw => $event) {
            $ruleset = $this->getRule($raw);
            if ($ruleset[1] == $type) {
                if ($this->regMatches($ruleset[0], $url)) {
                    $eventName = $event;
                }
            }
        }

        return $eventName;
    }

    /**
     * @param string $pageUrl
     * @param string $type
     * @return void
     */
    private function sendFbEvent(string $pageUrl, string $type = 'page'): void
    {
        $config = $this->config->get('plugins.' . $this->name);
        if (! isset($config['pixelid'])) {
            return;
        }
        $fbUrl = 'https://graph.facebook.com/v10.0/' . $config['pixelid'] . '/events?access_token=' . $config['accesstoken'];
        $_fbc = $_COOKIE['_fbc'];
        $_fbp = $_COOKIE['_fbp'];

        $eventName = $this->getEventName($config['rules'], $pageUrl, $type);

        $data = array(
            'event_name' => $eventName,
            'event_time' => time(),
            'event_source_url' => $pageUrl,
            'action_source' => 'website',
            'user_data' => array(
                'client_ip_address' => $_SERVER['REMOTE_ADDR'],
                'client_user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'fbc' => $_fbc,
                'fbp' => $_fbp
            ),
            'custom_data' => array(
                'referer' => $_SERVER['HTTP_REFERER']
            )
        );

        $payload = array(
            'data' => json_encode(array($data)),
            'access_token' => $config['accesstoken']
        );

        if ($config['testmode']) {
            $payload['test_event_code'] = $config['testevent'];
        }

        $ch = curl_init($fbUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $result = curl_exec($ch);
        $this->grav['log']->info(var_export($result, true));
        $this->grav['debugger']->addMessage($result);
        curl_close($ch);
    }

    /**
     * @param Event $e
     */
    public function onPageInitialized(Event $e): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->sendFbEvent($e['page']->url(true));
    }

    /**
     * @param Event $e
     */
    public function onFormProcessed(Event $e): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->sendFbEvent($e['action'], 'form');
    }
}