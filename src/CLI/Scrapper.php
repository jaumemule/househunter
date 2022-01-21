<?php

declare(strict_types=1);

namespace Pararius\CLI;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

final class Scrapper extends Command
{
    protected static $defaultName = 'app:scrap';

    public function __construct(
        private Connection $sql,
        private array $destinationUrl,
        private string $telegramChatId,
        private string $telegramToken,
        private int $sleepTimeInMinutes,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Will scrap Pararius. This tool is meant to massage your brain and drain stress');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $links = [];

        foreach ($this->destinationUrl as $url) {
            $crawler = $this->buildCrawler($url);
            $links = array_merge($links, $this->fetchLinks($crawler));
        }

        $this->guardIsBanned($links, $output);
        $isDbEmpty = $this->isDbEmpty();

        $notified = false;
        foreach ($links as $link) {
            if($this->persistIfDoesNotExist($link) === false) {
                $notified = true;
                if ($isDbEmpty === false) {
                    $this->sendMessage('CHAN CHAN!!!! NEW APARTMENT AVAILABLE! ' . 'https://www.pararius.com/' . $link);
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

    private function persistIfDoesNotExist(string $link): bool
    {
        $result = $this->sql->fetchAllAssociative(
            'SELECT id FROM listings WHERE url = :url',
            [
                'url' => $link,
            ]
        );

        if (count($result) === 0) {
            $this->sql->executeQuery(
                'INSERT INTO listings (url) VALUES (:url)',
                [
                    'url' => $link,
                ]
            );
            return false;
        }

        return true;
    }

    private function done(OutputInterface $output): void
    {
        $output->writeln('Iteration completed.');
        sleep($this->sleepTimeInMinutes * 60);
        exit(0);
    }

    private function buildCrawler(string $url): Crawler
    {
        // in case of local testing, uncomment this line
//        return new Crawler(file_get_contents('/var/www/src/example/index.html'));

        $userAgent = \Campo\UserAgent::random();
        $client = new Client(HttpClient::create(array(
            'headers' => array(
                'user-agent' => $userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.7',
                'Referer' => 'http://mydomain.net/',
                'Upgrade-Insecure-Requests' => '1',
                'Save-Data' => 'on',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ),
        )));
        $client->setServerParameter('HTTP_USER_AGENT', $userAgent);

        return $client->request('GET', $url);
    }

    private function fetchLinks(Crawler $crawler): array
    {
        $links = $crawler->filter('h2 > a')->each(function ($node) {
            $href = $node->extract(array('href'));
            return $links[] = $href[0];
        });
        return $links;
    }

    /**
     * @param array $links
     */
    private function guardIsBanned(array $links, OutputInterface $output): void
    {
        if (count($links) === 0) {
            $this->sendMessage('I cold not fetch data! Perhaps we are banned, ooops!');
            $this->done($output);
        }
    }

    protected function healthCheckControl(): void
    {
        $date = new \DateTimeImmutable();
        $hour = $date->format('H');
        $minutes = $date->format('i');
        $healthCheckHour = [1, 5, 9, 13, 17, 21];
        $minuteControl = $this->sleepTimeInMinutes > 60 ? 60 : $this->sleepTimeInMinutes;

        if (in_array($hour, $healthCheckHour) && $minutes <= $minuteControl) {
            $this->sendMessage('This is a health check control. The app it is still alive, but no new listings are found.');
        }
    }
}
