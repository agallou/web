<?php


namespace AppBundle\Mailchimp;

class Mailchimp
{
    private $client;

    public function __construct(\Mailchimp\Mailchimp $client)
    {
        $this->client = $client;
    }

    /**
     * Subscribe an address to a list
     *
     * @param string $list
     * @param string $email
     * @return \Illuminate\Support\Collection
     */
    public function subscribeAddress($list, $email)
    {
        return $this->client->put(
            'lists/' . $list . '/members/' . $this->getAddressId($email),
            ['status' => 'subscribed', 'email_address' => $email]
        );
    }

    const MAX_MEMBERS_PER_PAGE = 50;

    /**
     * @param string $list
     *
     * @return array
     */
    public function getAllSubscribedMembersAddresses($list)
    {
        $response = $this->client->get(
            'lists/' . $list . '/members',
            [
                'count' => 0,
                'status' => 'subscribed',
            ]
        );

        $totalItems = $response->get('total_items');

        $addresses = [];

        for ($i=0; $i<=ceil($totalItems / self::MAX_MEMBERS_PER_PAGE); $i++) {
            $response = $this->client->get(
                'lists/' . $list . '/members',
                [
                    'count' => self::MAX_MEMBERS_PER_PAGE,
                    'offset' => $i,
                    'fields' => 'members.email_address',
                    'status' => 'subscribed',
                ]
            );

            foreach ($response->all() as $member) {
                $addresses[] = $member->email_address;
            }
        }

        return array_unique($addresses);
    }

    /**
     * Unsubscribe an address from a list
     *
     * @param string $list
     * @param string $email
     * @return \Illuminate\Support\Collection
     */
    public function unSubscribeAddress($list, $email)
    {
        return $this->client->put(
            'lists/' . $list . '/members/' . $this->getAddressId($email),
            ['status' => 'unsubscribed', 'email_address' => $email]
        );
    }

    /**
     * Mailchimp uses a predictable id to allow upsert operations on subscriptions.
     * It's based on a hash of the email.
     *
     * @param string $email
     * @return string
     */
    private function getAddressId($email)
    {
        return md5($email);
    }


    public function createTemplate($title, $html)
    {
        return $this->client->post(
            'templates',
            [
                'name' => $title,
                'html' => $html,
            ]
        );
    }

    public function createCampaign($list, array $settings)
    {
        return $this->client->post(
            'campaigns',
            [
                'type' => 'regular',
                'recipients' => [
                    'list_id' => $list,
                ],
                'settings' => $settings
            ]
        );
    }
}
