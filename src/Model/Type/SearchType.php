<?php
namespace App\Model\Type;

use Cake\ElasticSearch\Type;
use Elastica\Query\QueryString;

class SearchType extends Type
{

    /**
     * Search the index
     *
     * @return void
     */
    public function search($lang, $version, $options = [])
    {
        $options += [
            'query' => '',
            'page' => 1,
            'sort' => ['_score'],
        ];
        $typeName = implode('-', [$version, $lang]);

        // This is a bit dangerous, but this class only has one real method.
        $this->name($typeName);
        $query = $this->query();

        $q = new QueryString($options['query']);
        $q->setPhraseSlop(2)
            ->setFields(['contents', 'title^3'])
            ->setDefaultOperator('AND')
            ->setFuzzyMinSim('0.7');

        $query->page($options['page'], 25)
            ->highlight([
                'pre_tags' => [''],
                'post_tags' => [''],
                'fields' => [
                    'contents' => [
                        'fragment_size' => 100,
                        'number_of_fragments' => 3
                    ],
                ],
            ])
            ->where(function ($builder) {
                return $builder->matchAll();
            })
            ->query($q);

        $results = $query->all();
        $rows = $results->map(function ($row) {
            $contents = '';
            if ($row->highlights()) {
                $contents = $row->highlights()['contents'];
            }
            return [
                'title' => $row->title ?: '',
                'url' => $row->url,
                'contents' => $contents,
            ];
        });
        return [
            'page' => $options['page'] ?: 1,
            'total' => $results->getTotalHits(),
            'data' => $rows,
        ];
    }
}
