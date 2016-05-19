<?php
namespace BrainSocket;

use Illuminate\Support\Facades\App;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class BrainSocketEventListener implements MessageComponentInterface
{
    protected $clients;
    protected $response;
    protected $matchArray = [];

    public function __construct(BrainSocketResponseInterface $response)
    {
        $this->clients = new \SplObjectStorage;
        $this->response = $response;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        echo "Connection Established! \n";
        $this->fill($conn->WebSocket->request->getQuery()->get('account_id'), $conn->resourceId);
        $this->clients->attach($conn);
        print_r($this->matchArray);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $obj = json_decode($msg);
        $recipient = $obj->account_id;

        if (array_key_exists($recipient, $this->matchArray)) {
            $resourceArray = $this->matchArray[$recipient][0];
            foreach ($resourceArray as $resource) {
                foreach ($this->clients as $client) {
                    if ($client->resourceId == $resource) {
                        $client->send($this->response->make($obj->body));
                    }
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        $account_id = $conn->WebSocket->request->getQuery()->get('account_id');
        $accountConnectionList = $this->matchArray[$conn->WebSocket->request->getQuery()->get('account_id')][0];

        unset($this->matchArray[$account_id][0][array_search($conn->resourceId, $accountConnectionList)]);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    public function fill($account_id, $resourceId)
    {
        if (!array_key_exists($account_id, $this->matchArray)) {
            $this->matchArray[$account_id] = [
                0 => [
                    0 => $resourceId,
                ]
            ];
            return;
        }
        $this->matchArray[$account_id][0][sizeof($this->matchArray[$account_id][0]) +1] = $resourceId;
    }
}
