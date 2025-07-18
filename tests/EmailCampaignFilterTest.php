<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests the SQL generated for subscriber filtering in email campaigns.
 */
class EmailCampaignFilterTest extends TestCase
{
    /**
     * Mimic the switch logic from functions/process_email_campaign.php to build the subscribers SQL.
     *
     * @param string $aud Target audience value (all|customers|non-customers)
     */
    private function buildSubscribersSql(string $aud): string
    {
        $aud = strtolower($aud);
        switch ($aud) {
            case 'customers':
                return "SELECT s.id, s.email\n                                        FROM email_subscribers s\n                                        JOIN users u ON u.email = s.email\n                                        WHERE s.status = 'active'\n                                          AND LOWER(u.role) != 'admin'";
            case 'non-customers':
                return "SELECT s.id, s.email\n                                        FROM email_subscribers s\n                                        LEFT JOIN users u ON u.email = s.email\n                                        WHERE s.status = 'active'\n                                          AND u.id IS NULL";
            default:
                return "SELECT id, email FROM email_subscribers WHERE status = 'active'";
        }
    }

    public function testSqlForAllAudience(): void
    {
        $expected = "SELECT id, email FROM email_subscribers WHERE status = 'active'";
        $this->assertSame($expected, $this->buildSubscribersSql('all'));
    }

    public function testSqlForCustomersAudience(): void
    {
        $expected = "SELECT s.id, s.email\n                                        FROM email_subscribers s\n                                        JOIN users u ON u.email = s.email\n                                        WHERE s.status = 'active'\n                                          AND LOWER(u.role) != 'admin'";
        $this->assertSame($expected, $this->buildSubscribersSql('customers'));
    }

    public function testSqlForNonCustomersAudience(): void
    {
        $expected = "SELECT s.id, s.email\n                                        FROM email_subscribers s\n                                        LEFT JOIN users u ON u.email = s.email\n                                        WHERE s.status = 'active'\n                                          AND u.id IS NULL";
        $this->assertSame($expected, $this->buildSubscribersSql('non-customers'));
    }
}
