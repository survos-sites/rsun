<?php

declare(strict_types=1);
namespace App\Tests\Controller;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
final class BillChatResetTest extends WebTestCase
{
    public function testClearRemovesOnlySelectedConversationAndRequiresCsrf(): void
    {
        $client=self::createClient();
        $session=self::getContainer()->get('session.factory')->createSession();
        $session->set('bill_chat_2026_0',[['role'=>'user','text'=>'Remember this question']]);
        $session->set('bill_chat_2025_0',[['role'=>'user','text'=>'Other bill history']]);
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(),$session->getId()));
        $crawler=$client->request('GET','/bills/chat');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section[aria-label="Conversation"]','Remember this question');
        $form=$crawler->selectButton('Clear conversation')->form();
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextNotContains('section[aria-label="Conversation"]','Remember this question');
        $client->request('GET','/bills/chat?year=2025');
        self::assertSelectorTextContains('section[aria-label="Conversation"]','Other bill history');
        $client->request('POST','/bills/chat/clear?year=2025',['_token'=>'invalid']);
        self::assertResponseStatusCodeSame(401);
        $client->request('GET','/bills/chat?year=2025');
        self::assertSelectorTextContains('section[aria-label="Conversation"]','Other bill history');
    }
}
