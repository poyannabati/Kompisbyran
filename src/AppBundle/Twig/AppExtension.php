<?php

namespace AppBundle\Twig;

use AppBundle\Enum\Countries;
use Symfony\Component\Translation\TranslatorInterface;
use AppBundle\Entity\User;
use AppBundle\Util\Util;

/**
 * Class AppExtension
 * @package AppBundle\Twig
 */
class AppExtension extends \Twig_Extension
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('country_name', [$this, 'countryName']),
            new \Twig_SimpleFilter('pronoun', [$this, 'pronoun']),
        ];
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return array(
            'want_to_learn_name'        => new \Twig_Function_Method($this, 'wantToLearnName'),
            'user_category_name_string' => new \Twig_Function_Method($this, 'userCategoryNameString'),
            'user_matched_categories'   => new \Twig_Function_Method($this, 'userMatchedCategories'),
            'selected_city'             => new \Twig_Function_Method($this, 'selectedCity'),
            'google_map_link'           => new \Twig_Function_Method($this, 'googleMapLink', [
                'is_safe' => ['html']
            ])
        );
    }

    /**
     * @param string $countryCode
     *
     * @return string
     */
    public function countryName($countryCode)
    {
        if ($countryCode) {
            return Countries::getName($countryCode);
        }

        return '';
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'app_extension';
    }

    /**
     * @param string $gender
     *
     * @return string
     */
    public function pronoun($gender)
    {
        if ('M' == $gender) {
            return 'han';
        } elseif ('F' == $gender) {
            return 'hon';
        }

        return 'hen';
    }

    /**
     * @param bool $wantToLearn
     * @return string
     */
    public function wantToLearnName($wantToLearn)
    {
        return $wantToLearn? $this->translator->trans('New'): $this->translator->trans('Established');
    }

    /**
     * @param User $user
     * @return string
     */
    public function userCategoryNameString(User $user)
    {
        $categoryNames  = array_values($user->getCategoryNames());

        return $this->toStringCategories($categoryNames);
    }

    /**
     * @param User $matchUser
     * @param User $user
     * @return string
     */
    public function userMatchedCategories(User $matchUser, User $user)
    {
        $matches = [];

        foreach ($matchUser->getCategoryNames() as $id => $name) {
            foreach ($user->getCategoryNames() as $userCatId => $userCatName) {
                if ($id == $userCatId) {
                    $matches[] = $userCatName;
                    break;
                }
            }
        }

        return $this->toStringCategories($matches);
    }

    /**
     * @param array $categories
     * @return array|string
     */
    private function toStringCategories(array $categories)
    {
        if (count($categories) > 1) {
            $lastCategory   = array_pop($categories);
            $categories = implode(', ', $categories) .' '.  $this->translator->trans('and') .' '. $lastCategory;
        } else {
            $categories = implode(', ', $categories);
        }

        return $categories;
    }

    /**
     * @param $string
     * @return string
     */
    public function googleMapLink($string)
    {
        return Util::googleMapLink($string);
    }

    /**
     * @param array $cities
     * @param $cityId
     * @return string|void
     */
    public function selectedCity($cities, $cityId)
    {
        foreach ($cities as $city) {
            if ($city->getId() == $cityId) {
                return 'SELECTED';
            }
        }

        return;
    }
}
