<?php

namespace Shopper\Framework\Services;

use function array_key_exists;
use function call_user_func;
use const FILTER_FLAG_PATH_REQUIRED;
use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;
use Illuminate\Support\Arr;
use function in_array;
use function is_array;
use Shopper\Framework\Exceptions\InvalidEmailException;

class Gravatar
{
    /**
     * Gravatar base url.
     *
     * @var string
     */
    private $publicBaseUrl = 'https://www.gravatar.com/avatar/';

    /**
     * Gravatar secure base url.
     *
     * @var string
     */
    private $secureBaseUrl = 'https://secure.gravatar.com/avatar/';

    /**
     * Email address to check.
     *
     * @var string
     */
    private $email;

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $fallback;

    /**
     * Override the default image fallback set in the config.
     * Can either be a public URL to an image or a valid themed image.
     * For more info, visit http://en.gravatar.com/site/implement/images/#default-image.
     *
     * @param bool|string $fallback
     *
     * @return $this
     */
    public function fallback($fallback)
    {
        if (
            filter_var($fallback, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)
            || in_array($fallback, ['mm', 'identicon', 'monsterid', 'wavatar', 'retro', 'blank'])
        ) {
            $this->fallback = $fallback;
        } else {
            $this->fallback = false;
        }

        return $this;
    }

    /**
     * Check if Gravatar has an avatar for the given email address.
     *
     * @param $email
     *
     * @throws InvalidEmailException
     */
    public function exists($email): bool
    {
        $this->checkEmail($email);
        $this->email = $email;
        $this->setConfig(['fallback' => 404]);
        $headers = @get_headers($this->buildUrl());

        return (bool) mb_strpos($headers[0], '200');
    }

    /**
     * Get the gravatar url.
     *
     * @param $email
     *
     * @throws InvalidEmailException
     */
    public function get($email): string
    {
        $this->checkEmail($email);
        $this->setConfig();
        $this->email = $email;

        return $this->buildUrl();
    }

    /**
     * Helper function to retrieve config settings.
     *
     * @param string      $value
     * @param null|string $default
     */
    protected function c($value, $default = null): bool
    {
        return array_key_exists($value, $this->config) ? $this->config[$value] : $default;
    }

    /**
     * Helper function for setting the config based on either:
     * 1. The name of a config group
     * 2. A custom array
     * 3. The default group in the config.
     *
     * @param null|array|string $group
     *
     * @return $this
     */
    private function setConfig($group = null): self
    {
        $default = [
            'size' => 80,
            'fallback' => 'mm',
            'secure' => false,
            'maximumRating' => 'g',
            'forceDefault' => false,
            'forceExtension' => 'jpg',
        ];

        if (is_array($group)) {
            $this->config = Arr::dot(array_replace_recursive($default, $group));
        } else {
            $this->config = Arr::dot($default);
        }

        return $this;
    }

    /**
     * Helper function to md5 hash the email address.
     */
    private function hashEmail(): string
    {
        return md5(mb_strtolower(trim($this->email)));
    }

    private function getExtension(): string
    {
        $v = $this->c('forceExtension');

        return $v ? '.' . $v : '';
    }

    private function buildUrl(): string
    {
        $url = $this->c('secure') === true ? $this->secureBaseUrl : $this->publicBaseUrl;
        $url .= $this->hashEmail();
        $url .= $this->getExtension();
        $url .= $this->getUrlParameters();

        return $url;
    }

    private function getUrlParameters(): string
    {
        $build = [];
        foreach (get_class_methods($this) as $method) {
            if (mb_substr($method, -mb_strlen('Parameter')) !== 'Parameter') {
                continue;
            }

            $called = call_user_func([$this, $method]);

            if ($called) {
                $build = array_replace($build, $called);
            }
        }

        return '?' . http_build_query($build);
    }

    /**
     * Check if the provided email address is valid.
     *
     * @param $email
     *
     * @throws InvalidEmailException
     */
    private function checkEmail($email)
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException(__('Please specify a valid email address'));
        }
    }
}
