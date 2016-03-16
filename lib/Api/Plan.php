<?php

namespace Zoho\Subscription\Api;

use Zoho\Subscription\Client\Client;

/**
 * Plan.
 *
 * @author Tristan Perchec <tristan.perchec@yproximite.com>
 * @author Tristan Bessoussa <tristan.bessoussa@gmail.com>
 *
 * @link https://www.zoho.com/subscriptions/api/v1/#plans
 */
class Plan extends Client
{
    protected $command = 'plans';
    protected $module = 'plan';
    
    protected function getId()
    {
        return $this['plan_code'];
    }
    
    protected function setId($id)
    {
        $this['plan_code'] = $id;
    }
    
    protected $base_template = [
        'name',
        'recurring_price',
        'interval',
        'interval_unit',
        'billing_cycles',
        'trial_period',
        'setup_fee',
        'product_id',
        'tax_id',
    ];
    
    protected function getCreateTemplate()
    {
        return array_merge($this->base_template, [
            'plan_code',
        ]);
    }
    
    protected function getUpdateTemplate()
    {
        return array_merge($this->base_template, [
            'end_of_term',
            'prorate',
        ]);
    }
    
    public static $addonTypes = [
        'recurring',
        'one_time',
    ];

    /**
     * Returns all plans.
     *
     * @param array $filters associative array of filters
     *
     * @throws \Exception
     *
     * @return array
     */
    public function listPlans($filters = [], $withAddons = true, $addonType = null)
    {
        $cacheKey = 'plans';
        $hit = $this->getFromCache($cacheKey);

        if (false === $hit) {
            $response = $this->request('GET', 'plans');
            if ($this->hasError()){
                return null;
            }
            $plans = $this->processResponse($response);
            $hit = $plans['plans'];

            $this->saveToCache($cacheKey, $hit);
        }

        $hit = $this->filterPlans($hit, $filters);

        if ($withAddons) {
            $hit = $this->getAddonsForPlan($hit, $addonType);
        }

        return $hit;
    }

    /**
     * Returns a Plan by its identifier.
     *
     * @param int $planCode
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getPlan($planCode)
    {
        $cacheKey = sprintf('plan_%s', $planCode);
        $hit = $this->getFromCache($cacheKey);

        if (false === $hit) {
            $response = $this->request('GET', sprintf('plans/%s', $planCode));

            $data = $this->processResponse($response);
            if ($this->hasError()){
                return null;
            }
            $plan = $data['plan'];

            $this->saveToCache($cacheKey, $plan);

            return $plan;
        }

        return $hit;
    }

    /**
     * get reccurent addons for given plan.
     *
     * @param array  $plans
     * @param string $addonType
     *
     * @return array
     */
    public function getAddonsForPlan($plans, $addonType)
    {
        $addonApi = new Addon($this->token, $this->organizationId, $this->cache, $this->ttl);

        foreach ($plans as &$plan) {
            $addons = [];

            foreach ($plan['addons'] as $planAddon) {
                $addon = $addonApi->getAddon($planAddon['addon_code']);

                if (null !== $addonType) {
                    if (($addon['type'] == $addonType) && (in_array($addonType, self::$addonTypes))) {
                        $addons[] = $addon;
                    }
                } else {
                    $addons[] = $addon;
                }
            }

            $plan['addons'] = $addons;
        }

        return $plans;
    }

    /**
     * filter given plans with given filters.
     *
     * @param array $plans
     * @param array $filters
     *
     * @return array
     */
    public function filterPlans($plans, $filters)
    {
        foreach ($filters as $key => $filter) {
            if (array_key_exists($key, current($plans))) {
                $plans = array_filter($plans, function ($element) use ($key, $filter) {
                    return $element[$key] == $filter;
                });
            }
        }

        return $plans;
    }
}
