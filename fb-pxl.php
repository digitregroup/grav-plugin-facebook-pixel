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
    private function sendFbEvent(string $pageUrl, string $type = 'page', array $userData = array()): void
    {
        $config = $this->config->get('plugins.' . $this->name);
        if (! isset($config['pixelid'])) {
            return;
        }
        $fbUrl = 'https://graph.facebook.com/' . $config['fbApiVersion'] . '/' . $config['pixelid'] . '/events?access_token=' . $config['accesstoken'];
        $_fbc = $_COOKIE['_fbc'] ?? '';
        $_fbp = $_COOKIE['_fbp'] ?? '';

        $rules = array_key_exists('rules', $config) ? $config['rules'] : Array();

        $eventName = $this->getEventName($rules, $pageUrl, $type);

        $referer = '';
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
        }

        $data = array(
            'event_name' => $eventName,
            'event_time' => time(),
            'event_source_url' => $pageUrl,
            'action_source' => 'website',
            'user_data' => array(
                'client_ip_address' => $_SERVER['REMOTE_ADDR'],
                'fbc' => $_fbc,
                'fbp' => $_fbp
            ),
            'custom_data' => array(
                'referer' => $referer
            )
        );

        if(isset($_SERVER['HTTP_USER_AGENT'])) {
            $data['user_data'] = array_merge($data['user_data'], ['client_user_agent' => $_SERVER['HTTP_USER_AGENT']]);
        }

        if ($type == 'form' && count($userData) > 0) {
            $data['user_data'] = array_merge($data['user_data'], $userData);
        }

        $payload = array(
            'data' => json_encode(array($data)),
            'access_token' => $config['accesstoken']
        );

        if ($config['testmode']) {
            $payload['test_event_code'] = $config['testevent'] ?? 'TEST';
        }

        try {
            $ch = curl_init($fbUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $result = curl_exec($ch);
            curl_close($ch);

            //$this->grav['log']->info('[FB Pxl Plugin (sent)] '.var_export($payload, true));
            //$this->grav['log']->info('[FB Pxl Plugin (response)] '.var_export($result, true));
            $this->grav['debugger']->addMessage($result);
        }catch (\Exception $e) {
            $this->grav['log']->error(print_r($e, true));
        }
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
        $email = $e['form']->value('email') ?? '';
        $emailHash = hash('sha256', $email);
        $phone = $e['form']->value('phone') ?? '';
        $phoneHash = hash('sha256', $phone);

        $userData = array(
            'em' => $emailHash,
            'ph' => $phoneHash
        );

        $this->sendFbEvent($e['action'], 'form', $userData);
    }
}