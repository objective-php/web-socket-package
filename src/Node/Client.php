<?php
/**
 * Created by PhpStorm.
 * User: gde
 * Date: 07/09/2017
 * Time: 13:47
 */

namespace ObjectivePHP\Package\WebSocketServer\Node;


use Hoa\Websocket\Node;

class Client implements \JsonSerializable
{

    /** @var  string */
    protected $identifier;

    /** @var Node[]  */
    protected $nodes = [];

    /**
     * @var array Arbitrary properties
     */
    protected $properties = [];

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     * @return $this
     */
    public function setIdentifier(string $identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * @return array
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @param array $nodes
     */
    public function setNodes(array $nodes)
    {
        $this->nodes = [];
        foreach ($nodes as $node) $this->addNode($node);
    }

    /**
     * @param Node $node
     * @return $this
     */
    public function addNode(Node $node)
    {
        $this->nodes[] = $node;

        return $this;
    }

    /**
     * @param $property
     * @param $value
     * @return $this
     */
    public function addProperty($property, $value)
    {
        $this->properties[$property] = $value;

        return $this;
    }

    /**
     * @param $property
     * @return mixed|null
     */
    public function getProperty($property)
    {
        return isset($this->properties[$property]) ? $this->properties[$property] : null;
    }

    /**
     * @param Node $node
     * @return $this
     */
    public function setActiveNode(Node $node)
    {
        $this->addProperty('activeNode', $node->getId());

        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getActiveNode()
    {
        return $this->getProperty('activeNode');
    }


    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $data = ['identifier' => $this->getIdentifier()];
        foreach($this->properties as $property => $value)
        {
            $data[$property] = $value;
        }

        return $data;
    }

    /**
     * @param Node $nodeToRemove
     * @return $this
     */
    public function removeNode(Node $nodeToRemove)
    {
        foreach($this->nodes as $id => $node)
        {
            if($node === $nodeToRemove) {
                unset($this->nodes[$id]);
                $this->nodes = array_values($this->nodes);
                break;
            }
        }

        return $this;
    }

}