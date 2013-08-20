<?php

namespace Gliph\Traversal;

use Gliph\Graph\DirectedAdjacencyGraph;
use Gliph\Visitor\DepthFirstVisitorInterface;

class DepthFirst {

    /**
     * Perform a depth-first traversal on the provided graph.
     *
     * @param DirectedAdjacencyGraph $graph
     *   The graph on which to perform the depth-first search.
     * @param DepthFirstVisitorInterface $visitor
     *   The visitor object to use during the traversal.
     * @param mixed $start
     *   A queue of vertices to ensure are visited. The traversal will deque
     *   them in order and visit them.
     *
     * @throws \OutOfBoundsException
     *   Thrown if an invalid $start parameter is provided.
     */
    public static function traverse(DirectedAdjacencyGraph $graph, DepthFirstVisitorInterface $visitor, $start = NULL) {
        if ($start === NULL) {
            $queue = self::find_sources($graph, $visitor);
        }
        else if ($start instanceof \SplDoublyLinkedList) {
            $queue = $start;
        }
        else if (is_object($start)) {
            $queue = new \SplDoublyLinkedList();
            $queue->push($start);
        }
        else {
            throw new \OutOfBoundsException('Vertices must be objects; non-object start vertex provided.');
        }

        if ($queue->isEmpty()) {
            throw new \RuntimeException('No start vertex or vertices were provided, and no source vertices could be found in the provided graph.', E_WARNING);
        }

        $visiting = new \SplObjectStorage();
        $visited = new \SplObjectStorage();

        $visit = function($vertex) use ($graph, $visitor, &$visit, $visiting, $visited) {
            if ($visiting->contains($vertex)) {
                $visitor->onBackEdge($vertex, $visit);
            }
            else if (!$visited->contains($vertex)) {
                $visiting->attach($vertex);

                $visitor->onStartVertex($vertex, $visit);

                $graph->eachAdjacent($vertex, function($to) use ($vertex, &$visit, $visitor) {
                    $visitor->onExamineEdge($vertex, $to, $visit);
                    $visit($to);
                });

                $visitor->onFinishVertex($vertex, $visit);

                $visiting->detach($vertex);
                $visited->attach($vertex);
            }
        };

        while (!$queue->isEmpty()) {
            $vertex = $queue->shift();
            $visit($vertex);
        }
    }

    /**
     * Finds source vertices in a DirectedAdjacencyGraph, then enqueues them.
     *
     * @param DirectedAdjacencyGraph $graph
     * @param DepthFirstVisitorInterface $visitor
     *
     * @return \SplQueue
     */
    public static function find_sources(DirectedAdjacencyGraph $graph, DepthFirstVisitorInterface $visitor) {
        $incomings = new \SplObjectStorage();
        $queue = new \SplQueue();

        $graph->eachEdge(function ($edge) use (&$incomings) {
            if (!isset($incomings[$edge[1]])) {
                $incomings[$edge[1]] = new \SplObjectStorage();
            }
            $incomings[$edge[1]]->attach($edge[0]);
        });

        // Prime the queue with vertices that have no incoming edges.
        $graph->eachVertex(function($vertex) use ($queue, $incomings, $visitor) {
            if (!$incomings->contains($vertex)) {
                $queue->push($vertex);
                // TRUE second param indicates source vertex
                $visitor->onInitializeVertex($vertex, TRUE, $queue);
            }
            else {
                $visitor->onInitializeVertex($vertex, FALSE, $queue);
            }
        });

        return $queue;
    }
}