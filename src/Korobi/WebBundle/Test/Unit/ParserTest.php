<?php

namespace Korobi\WebBundle\Test\Unit;

use Korobi\WebBundle\Document\Chat;
use Korobi\WebBundle\Parser\LogParser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class ParserTest
 * @see LogParser
 * @package Korobi\WebBundle\Test\Unit
 */
class ParserTest extends WebTestCase {

    public function testShutUpPhpUnit() {
        $this->assertTrue(true);
    }

    public function testParseJoin() {
        /** @see LogParser::parseJoin */
        $chat = $this->getMockBuilder('Korobi\WebBundle\Document\Chat')
            ->disableOriginalConstructor()
            ->getMock();

        $chat->expects($this->any())->method("getDate")->will($this->returnValue(new \DateTime("@0")));
        $chat->expects($this->any())->method("getActorName")->will($this->returnValue("TestUser"));
        $chat->expects($this->any())->method("getActorPrefix")->will($this->returnValue("OPERATOR"));
        $chat->expects($this->any())->method("getActorHostname")->will($this->returnValue("user@host"));

        $this->assertEquals('<span class="irc--09-99">@</span>TestUser (user@host) joined the channel', LogParser::parseJoin($chat));
    }

    public function testParseMessage() {
        /** @see LogParser::parseMessage */
        $chat = $this->getMockBuilder('Korobi\WebBundle\Document\Chat')
            ->disableOriginalConstructor()
            ->getMock();

        $chat->expects($this->any())->method("getDate")->will($this->returnValue(new \DateTime("@0")));
        $chat->expects($this->any())->method("getActorName")->will($this->returnValue("TestUser"));
        $chat->expects($this->any())->method("getActorPrefix")->will($this->returnValue("OPERATOR"));
        $chat->expects($this->any())->method("getMessage")->will($this->returnValue("\x0307,04Hello!"));
        $this->assertEquals('<span class="irc--07-04">Hello!</span>', LogParser::parseMessage($chat));
    }


    public function testParseMessageWithReverseColour() {
        /** @see LogParser::parseMessage */
        $chat = $this->getMockBuilder('Korobi\WebBundle\Document\Chat')
            ->disableOriginalConstructor()
            ->getMock();

        $chat->expects($this->any())->method("getDate")->will($this->returnValue(new \DateTime("@0")));
        $chat->expects($this->any())->method("getActorName")->will($this->returnValue("TestUser"));
        $chat->expects($this->any())->method("getActorPrefix")->will($this->returnValue("OPERATOR"));
        $chat->expects($this->any())->method("getMessage")->will($this->returnValue("\x0307,04\x16Hello!"));
        $this->assertEquals('<span class="irc--07-04"><span class="irc--04-07">Hello!</span></span>', LogParser::parseMessage($chat));
    }

    public function testParseMessageWithSingleColourAsReset() {
        /** @see LogParser::parseMessage */
        $chat = $this->getMockBuilder('Korobi\WebBundle\Document\Chat')
            ->disableOriginalConstructor()
            ->getMock();

        // {3}13Red_Sky{3} plays {3}0,04 Red 7 {3}  {3}to {3}06Kashike

        $chat->expects($this->any())->method("getDate")->will($this->returnValue(new \DateTime("@0")));
        $chat->expects($this->any())->method("getActorName")->will($this->returnValue("TestUser"));
        $chat->expects($this->any())->method("getActorPrefix")->will($this->returnValue("OPERATOR"));
        $chat->expects($this->any())->method("getMessage")->will($this->returnValue("\x0313Red_Sky\x03 plays \x030,04 Red 7 \x03 \x03 to \x0306Kashike"));
        $this->assertEquals('<span class="irc--13-99">Red_Sky</span> plays <span class="irc--00-04"> Red 7 </span>  to <span class="irc--06-04">Kashike</span>', LogParser::parseMessage($chat));
    }



}
