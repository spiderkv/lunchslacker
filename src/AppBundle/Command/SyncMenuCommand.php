<?php

namespace AppBundle\Command;

use AppBundle\Document\Meal;
use AppBundle\Document\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncLunchCommand
 * @package AppBundle\Command
 */
class SyncMenuCommand extends ContainerAwareCommand
{

    /**
     * @var string[]
     */
    private static $days = [
        'NEDELJA' => 'sunday',
        'PONEDELJAK' => 'monday',
        'UTORAK' => 'tuesday',
        'SREDA' => 'wednesday',
        'CETVRTAK' => 'thursday',
        'PETAK' => 'friday',
        'SUBOTA' => 'saturday'
    ];

    /**
     * @var User[]
     */
    private static $userMap = [];

    protected function configure()
    {
        $this
            ->setName('app:sync-menu')
            ->setDescription('Synchronizes menu from Google Spreadsheet.');
    }

    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sheet = $this->getSpreadsheet();
        foreach ($sheet as $day => $menu) {
            foreach ($menu as $meal => $users) {
                foreach ($users as $user) {
                    $this->saveMeal($day, $user, $meal);
                }
            }
        }
    }

    /**
     * @param string $day
     * @param string $userName
     * @param string $mealName
     */
    private function saveMeal($day, $userName, $mealName)
    {
        $date = $this->resolveDate($day);
        $user = $this->getUserByName($userName);
        $dm = $this->getDocumentManager();
        if ($date && $user) {
            $meal = new Meal();
            $meal->setDate($this->resolveDate($day))
                ->setUser($this->getUserByName($userName))
                ->setMeal($mealName)
            ;
            $dm->persist($meal);
        }
        $dm->flush();
    }

    /**
     * @param $dayName
     * @return string
     */
    private function resolveDate($dayName)
    {
        $currentDayOfWeek = date('N', strtotime(date("l")));
        $queriedDayOfWeek = date('N', strtotime(array_search($dayName, array_keys(self::$days))));
        if($currentDayOfWeek < $queriedDayOfWeek) {
            return date('Y-m-d',  strtotime('next ' .  self::$days[$dayName]));
        } else if ($currentDayOfWeek > $queriedDayOfWeek) {
            return date('Y-m-d',  strtotime('last ' .  self::$days[$dayName]));
        } else {
            return date('Y-m-d');
        }
    }

    /**
     * @param $name
     * @return User|null
     */
    private function getUserByName($name)
    {
        $this->cacheUser($name);

        return self::$userMap[$name];
    }

    /**
     * @param string $name
     */
    private function cacheUser($name)
    {
        if (!isset(self::$userMap[$name])) {
            $user = $this->getDocumentManager()->getRepository('AppBundle:User')->findOneBy(['name' => $name]);
            self::$userMap[$name] = $user;
        }
    }

    /**
     * @return array
     */
    private function getSpreadsheet()
    {
        $buzz = $this->getContainer()->get('buzz');
        $response = $buzz->get($this->getContainer()->getParameter('google_spreadsheet_menu_tsv'));

        return $this->parseContent($response->getContent());
    }

    /**
     * @param string $content
     * @return array
     */
    private function parseContent($content)
    {
        $lines = explode("\r\n", $content);
        $result = [];
        $day = null;
        foreach ($lines as $line) {
            $lineArr = explode("\t", $line);

            if (array_key_exists($lineArr[0], self::$days)) {
                $day = $lineArr[0];
                continue;
            }

            if (is_null($day)) {
                continue;
            }

            if (!isset($result[$day])) {
                $result[$day] = [];
            }

            if (!isset($result[$day][$lineArr[0]])) {
                $result[$day][$lineArr[0]] = [];
            }

            for ($i = 2; $i <= count($lineArr); $i++) {
                if (!empty($lineArr[$i]))
                $result[$day][$lineArr[0]][] = $lineArr[$i];
            }

        }

        return $result;
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectManager|object
     */
    private function getDocumentManager()
    {
        return $this->getContainer()->get('doctrine_mongodb')->getManager();
    }

}
