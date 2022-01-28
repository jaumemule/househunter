<?php

declare(strict_types=1);

namespace HouseHunter\CLI;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

final class Scrapper extends Command
{
    const WITH_LAZINESS = 'no-laziness';
    protected static $defaultName = 'app:scrap';

    public function __construct(
        private Connection $sql,
        private string $destinationUrl,
        private string $telegramChatId,
        private string $telegramToken,
        private int $sleepTimeInMinutes,
        private bool $isFakeRequest,
    ) {
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        if ($this->sleepTimeInMinutes < 15) {
            $this->sleepTimeInMinutes = 15; # abuse control
        }
    }

    protected function configure(): void
    {
        $this->setDescription('Will scrap houses. This tool is meant to massage your brain and drain stress');
        $this->addOption(self::WITH_LAZINESS, 'i', InputArgument::OPTIONAL, 'If debugging, sleep will trigger. This is to ignore it and manually execute it', false);
        $this->addArgument(self::WITH_LAZINESS, InputArgument::OPTIONAL, 'If debugging, sleep will trigger. This is to ignore it and manually execute it', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $withLaziness = $input->getArgument(self::WITH_LAZINESS);

        if ($this->isFakeRequest === true && $withLaziness === false) {
            echo "will sleep and do nothing";
            sleep(1000000000000);
        }

        $links = [];
        $text = [];

        $urls = explode('|', $this->destinationUrl);

        foreach ($urls as $url) {
            $crawler = $this->buildCrawler($url);
            $platform = $this->definePlatform($url);
            $links = array_merge($links, $this->fetchLinks($platform, $crawler));
            $this->guardIsBanned($links, $platform, $url);
            $text = array_merge($text, $this->fetchText($platform, $crawler));
        }

        $isDbEmpty = $this->isDbEmpty();

        $notified = false;
        foreach ($links as $key => $link) {
            $name = null;
            if (key_exists($key, $text)) {
                $name = $text[$key];
            }
            if($this->persistIfDoesNotExist($link, $name) === false) {
                $notified = true;
                if ($isDbEmpty === false) {
                    $this->sendMessage("CHAN CHAN!!!! New listing!\n\n" . 'in: ' . $platform . "\n\n" . $name . "\n\n" . $link ."\n\n");
                }
            }
        }

        if ($notified === false) {
            $this->healthCheckControl();
        }

        $this->done($output);
        exit(0);
    }

    private function sendMessage($messaggio) {
        if ($this->isFakeRequest === true) {
            echo $messaggio ."\n";
            return true;
        }

        $url = "https://api.telegram.org/bot" . $this->telegramToken . "/sendMessage?chat_id=" . $this->telegramChatId;
        $url = $url . "&text=" . urlencode($messaggio);
        $ch = curl_init();
        $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        );
        curl_setopt_array($ch, $optArray);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function isDbEmpty(): bool
    {
        $result = $this->sql->fetchAllAssociative(
            'SELECT id FROM listings',
        );

        return count($result) === 0;
    }

    private function persistIfDoesNotExist(string $link, ?string $name = null): bool
    {
        $result = $this->sql->fetchAllAssociative(
            'SELECT id FROM listings WHERE url = :url',
            [
                'url' => $link,
            ]
        );

        if (count($result) === 0) {
            $this->sql->executeQuery(
                'INSERT INTO listings (url, name) VALUES (:url, :name)',
                [
                    'url' => $link,
                    'name' => $name,
                ]
            );
            return false;
        }

        return true;
    }

    private function done(OutputInterface $output): void
    {
        $output->writeln('Iteration completed.');
        if ($this->isFakeRequest === true) {
            exit(0);
        }
        sleep($this->sleepTimeInMinutes * 60);
        exit(0);
    }

    private function buildCrawler(string $url): Crawler
    {
        // in case of local testing, uncomment this line
        if ($this->isFakeRequest === true) {
            return new Crawler(file_get_contents('/var/www/src/example/funda.html'));
        }

        $faker = \Faker\Factory::create();

        $userAgent = \Campo\UserAgent::random();
//        $userAgent = $faker->userAgent;

        $client = new Client(HttpClient::create(array(
            'headers' => array(
                'user-agent' => $userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => $faker->locale,
                'Referer' => $faker->url,
                'Upgrade-Insecure-Requests' => '1',
                'Save-Data' => 'on',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ),
        )));
        $client->setServerParameter('HTTP_USER_AGENT', $userAgent);

        return $client->request('GET', $url);
    }

    private function fetchLinks(string $platform, Crawler $crawler): array
    {
        $selector = '';
        $baseUrl = '';

        if ($platform === 'Pararius') {
            $selector = 'h2 > a';
            $baseUrl = 'https://www.pararius.com';
        } elseif ($platform === 'Funda') {
            $selector = 'div[class="search-result__header-title-col"] > a[data-object-url-tracking="resultlist"]';
            $baseUrl = 'https://www.funda.nl';
        }

        $links = $crawler->filter($selector)->each(function ($node) use ($baseUrl) {
            $href = $node->extract(array('href'));
            return $links[] = $baseUrl . $href[0];
        });

        return array_unique($links);
    }

    private function fetchText(string $platform, Crawler $crawler): array
    {
        $selector = '';

        if ($platform === 'Pararius') {
            $selector = 'h2';
        } elseif ($platform === 'Funda') {
            $selector = 'h2[data-test-search-result-header-title]'; // TODO
        }

        $text = $crawler->filter($selector)->each(function ($node) {
            $extracted = $node->text();
            return $text[] = $extracted;
        });

        return $text;
    }

    private function guardIsBanned(array $links, string $platform, string $url): void
    {
        if (count($links) === 0) {
            $this->sendMessage('I cold not fetch data! Perhaps we are banned in '.$platform.', while fetching '. $url .', ooops!');
        }
    }

    protected function healthCheckControl(): void
    {
        $date = new \DateTimeImmutable();
        $hour = $date->format('H');
        $minutes = $date->format('i');
        $healthCheckHour = [12];
        $minuteControl = $this->sleepTimeInMinutes > 60 ? 60 : $this->sleepTimeInMinutes;

        if (in_array($hour, $healthCheckHour) && $minutes <= $minuteControl) {
            $this->sendMessage('This is a daily health check control. The app it is still alive, but no new listings are found.');
        }
    }

    /**
     * @param string $url
     * @return array
     */
    private function definePlatform(string $url): string
    {
        $urlInfo = parse_url($url);

        $baseUrl = $urlInfo['host']; //hostname
        if ($baseUrl === 'www.funda.nl') {
            $platform = 'Funda';
        } else {
            $platform = 'Pararius';
        }
        return $platform;
    }
}
