<?php

namespace Accord\MandrillSwiftMailerBundle\Tests\SwiftMailer;

use Accord\MandrillSwiftMailerBundle\SwiftMailer\MandrillTransport;

class MandrillTransportTest extends \PHPUnit_Framework_TestCase{

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Swift_Events_EventDispatcher
     */
    protected $dispatcher;

    protected function setUp()
    {
        $this->dispatcher = $this->getMock('\Swift_Events_EventDispatcher');
    }

    public function testTags()
    {

        $transport = new MandrillTransport($this->dispatcher);

        $message = new \Swift_Message('Test Subject', 'Foo bar');

        $message
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;

        $message->getHeaders()->addTextHeader('X-MC-Tags', 'foo,bar');

        $mandrillMessage = $transport->getMandrillMessage($message);

        $this->assertEquals('foo', $mandrillMessage['tags'][0]);
        $this->assertEquals('bar', $mandrillMessage['tags'][1]);

    }

    public function testMultipartNullContentType()
    {

        $transport = new MandrillTransport($this->dispatcher);

        $message = new \Swift_Message('Test Subject', 'Foo bar');

        $message
            ->addPart('<p>Foo bar</p>', 'text/html')
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;

        $mandrillMessage = $transport->getMandrillMessage($message);

        $this->assertEquals('Foo bar', $mandrillMessage['text'], 'Multipart email should contain plaintext message');
        $this->assertEquals('<p>Foo bar</p>', $mandrillMessage['html'], 'Multipart email should contain HTML message');

    }

    public function testMultipartPlaintextFirst()
    {
        $transport = new MandrillTransport($this->dispatcher);

        $message = new \Swift_Message('Test Subject', 'Foo bar', 'text/plain');

        $message
            ->addPart('<p>Foo bar</p>', 'text/html')
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;

        $mandrillMessage = $transport->getMandrillMessage($message);

        $this->assertEquals('Foo bar', $mandrillMessage['text'], 'Multipart email should contain plaintext message');
        $this->assertEquals('<p>Foo bar</p>', $mandrillMessage['html'], 'Multipart email should contain HTML message');

    }

    public function testMultipartHtmlFirst()
    {
        $transport = new MandrillTransport($this->dispatcher);

        $message = new \Swift_Message('Test Subject', '<p>Foo bar</p>', 'text/html');

        $message
            ->addPart('Foo bar', 'text/plain')
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;

        $mandrillMessage = $transport->getMandrillMessage($message);

        $this->assertEquals('Foo bar', $mandrillMessage['text'], 'Multipart email should contain plaintext message');
        $this->assertEquals('<p>Foo bar</p>', $mandrillMessage['html'], 'Multipart email should contain HTML message');
    }

    public function testPlaintextMessage()
    {
        $transport = new MandrillTransport($this->dispatcher);

        $message = new \Swift_Message('Test Subject', 'Foo bar', 'text/plain');

        $message
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;

        $mandrillMessage = $transport->getMandrillMessage($message);

        $this->assertNull($mandrillMessage['html'], 'Plaintext only email should not contain HTML counterpart');
        $this->assertEquals('Foo bar', $mandrillMessage['text']);
    }

    public function testHtmlMessage()
    {
        $transport = new MandrillTransport($this->dispatcher);

        $message = new \Swift_Message('Test Subject', '<p>Foo bar</p>', 'text/html');

        $message
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;

        $mandrillMessage = $transport->getMandrillMessage($message);

        $this->assertNull($mandrillMessage['text'], 'HTML only email should not contain plaintext counterpart');
        $this->assertEquals('<p>Foo bar</p>', $mandrillMessage['html']);
    }

    public function testMessage()
    {

        $transport = new MandrillTransport($this->dispatcher);

        $message = new \Swift_Message('Test Subject', '<p>Foo bar</p>', 'text/html');

        $attachment = new \Swift_Attachment('FILE_CONTENTS', 'filename.txt', 'text/plain');
        $message->attach($attachment);

        $message
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
            ->addCc('cc-1@example.com', 'CC 1 Name')
            ->addCc('cc-2@example.com', 'CC 2 Name')
            ->addBcc('bcc-1@example.com', 'BCC 1 Name')
            ->addBcc('bcc-2@example.com', 'BCC 2 Name')
            ->addReplyTo('reply-to@example.com', 'Reply To Name')
        ;

        $mandrillMessage = $transport->getMandrillMessage($message);

        $this->assertEquals('<p>Foo bar</p>', $mandrillMessage['html']);
        $this->assertNull($mandrillMessage['text'], 'HTML only email should not contain plaintext counterpart');
        $this->assertEquals('Test Subject', $mandrillMessage['subject']);
        $this->assertEquals('from@example.com', $mandrillMessage['from_email']);
        $this->assertEquals('From Name', $mandrillMessage['from_name']);

        $this->assertMandrillMessageContainsRecipient('to@example.com', 'To Name', 'to', $mandrillMessage);
        $this->assertMandrillMessageContainsRecipient('cc-1@example.com', 'CC 1 Name', 'cc', $mandrillMessage);
        $this->assertMandrillMessageContainsRecipient('cc-2@example.com', 'CC 2 Name', 'cc', $mandrillMessage);
        $this->assertMandrillMessageContainsRecipient('bcc-1@example.com', 'BCC 1 Name', 'bcc', $mandrillMessage);
        $this->assertMandrillMessageContainsRecipient('bcc-2@example.com', 'BCC 2 Name', 'bcc', $mandrillMessage);

        $this->assertMandrillMessageContainsAttachment('text/plain', 'filename.txt', 'FILE_CONTENTS', $mandrillMessage);

        $this->assertArrayHasKey('Reply-To', $mandrillMessage['headers']);
        $this->assertEquals('reply-to@example.com <Reply To Name>', $mandrillMessage['headers']['Reply-To']);



    }

    /**
     * @param string $type
     * @param string $name
     * @param string $content
     * @param array $message
     */
    protected function assertMandrillMessageContainsAttachment($type, $name, $content, array $message){
        foreach($message['attachments'] as $attachment){
            if($attachment['type'] === $type && $attachment['name'] === $name){
                $this->assertEquals($content, base64_decode($attachment['content']));
                return;
            }
        }
        $this->fail(sprintf('Expected Mandrill message to contain a %s attachment named %s', $type, $name));
    }

    /**
     * @param string $email
     * @param string $name
     * @param string $type
     * @param array $message
     */
    protected function assertMandrillMessageContainsRecipient($email, $name, $type, array $message){
        foreach($message['to'] as $recipient){
            if($recipient['email'] === $email && $recipient['name'] === $name && $recipient['type'] === $type){
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail(sprintf('Expected Mandrill message "to" contain %s recipient %s <%s>', $type, $email, $name));
    }

}