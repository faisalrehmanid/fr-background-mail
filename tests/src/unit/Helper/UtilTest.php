<?php

namespace FRUnitTest\BackgroundMail\Helper;

use PHPUnit\Framework\TestCase;
use FR\BackgroundMail\Helper\Util;

class UtilTest extends TestCase
{
    protected static $Util;

    /**
     * This method is executed only once per class
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$Util = new Util();
    }

    /**
     * @test
     * @covers FR\BackgroundMail\Helper\Util::emailToArray
     * 
     * @return void
     */
    public function emailToArray()
    {
        $tests = [
            [
                'email'   => ' invalid-email ',
                'result'  => ''
            ],
            [
                'email'   => ' VAlid@EMAIl.com ',
                'result'  => 'valid@email.com'
            ],
            [
                'email'   => ' VAlid@EMAIl.com:With Name ',
                'result'  => ['valid@email.com' => 'With Name']
            ],
            [
                'email'   => ' VAlid@EMAIl.com: ',
                'result'  => 'valid@email.com'
            ]
        ];

        foreach ($tests as $i => $test) {
            $result = self::$Util::emailToArray($test['email']);
            $this->assertEquals($test['result'], $result);
        }
    }

    /**
     * @test
     * @covers FR\BackgroundMail\Helper\Util::emailsToArray
     * 
     * @return void
     */
    public function emailsToArray()
    {
        $tests = [
            [
                'emails'   => ' invalid-email; invalid-email:NAME ',
                'result'  => []
            ],
            [
                'emails'   => ' VAlid@EMAIl.com ',
                'result'  => [
                    'valid@email.com'
                ]
            ],
            [
                'emails'   => ' VAlid@EMAIl.com:With Name ',
                'result'  => [
                    'valid@email.com' => 'With Name'
                ]
            ],
            [
                'emails'   => ' invaliD; VAlid@EMAil.com:; ; VAlid@EMAIl.com:With Name; ;; ',
                'result'  => [
                    'valid@email.com',
                    'valid@email.com' => 'With Name'
                ]
            ]
        ];

        foreach ($tests as $i => $test) {
            $result = self::$Util::emailsToArray($test['emails']);
            $this->assertEquals($test['result'], $result);
        }
    }

    /**
     * @test
     * @covers FR\BackgroundMail\Helper\Util::validateEmail
     * 
     * @return void
     */
    public function validateEmail()
    {
        $tests = [
            [
                'email'   => ' invalid-email ',
                'result'  => ''
            ],
            [
                'email'   => ' VAlid@EMAIl.com ',
                'result'  => 'valid@email.com'
            ]
        ];

        foreach ($tests as $i => $test) {
            $result = self::$Util::validateEmail($test['email']);
            $this->assertEquals($test['result'], $result);
        }
    }

    /**
     * @test
     * @covers FR\BackgroundMail\Helper\Util::generateUniqueId
     * 
     * @return void
     */
    public function generateUniqueId()
    {
        $tests = [
            [
                'length'   => 32,
            ],
            [
                'length'   => 64,
            ],
            [
                'length'   => 128,
            ]
        ];

        foreach ($tests as $i => $test) {
            $result = self::$Util::generateUniqueId($test['length']);
            $this->assertEquals($test['length'], strlen($result));
        }
    }
}
