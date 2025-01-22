<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\ResourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use function Symfony\Component\String\s;

class FindService
{
    public function __construct(
        private readonly ProjectRepository  $projectRepository,
        private readonly ResourceRepository $resourceRepository,
        private readonly EntityManagerInterface $entityManager
    )
    {

    }

    public function findByWord(User $user, string $projectUuid, string $word, string $replace): array
    {
        $result = [];
        $queryBuilder = $this->projectRepository->getOneByUuidQueryBuilder($user, $projectUuid);
        $camelCase = s($word)->camel()->toString();
        $snackCase = s($word)->snake()->toString();
        $word = addslashes($word);
        foreach ($this
                     ->resourceRepository
                     ->findByWordQueryBuilder(
                         word: $word,
                         camelCase: $camelCase,
                         snackCase: $snackCase,
                         queryBuilder: $queryBuilder)
                     ->getQuery()
                     ->getOneOrNullResult()
                     ?->getResources() ?? [] as $item) {

            if (!empty($item->getContent())) {
                $file = explode("\n", $item->getContent());
                if (!empty($file)) {
                    $lineNumber = 1;
                    $items_ = [];
                    foreach ($file as $fileItem) {

                        if (
                            stripos($fileItem, $word) !== false ||
                            stripos($fileItem, $snackCase) !== false ||
                            stripos($fileItem, $camelCase) !== false
                        ) {

                            $fileItemReplace = $fileItem;
                            $word = stripslashes($word);
                            $snackCase = stripslashes($snackCase);
                            $camelCase = stripslashes($camelCase);
                            $fileItemReplace = str_replace($word, $replace, $fileItemReplace);
                            $fileItemReplace = str_replace($snackCase, $replace, $fileItemReplace);
                            $fileItemReplace = str_replace($camelCase, $replace, $fileItemReplace);

                            $items_[] = [
                                'lineNumber' => $lineNumber,
                                'line' => $fileItem,
                                'lineReplace' => $fileItemReplace,
                                'item' => $item,
                            ];

                        }

                        $lineNumber++;
                    }


                    $pathItemReplace = $item->getPath();
                    $pathItemReplace = str_replace($word, $replace, $pathItemReplace);
                    $pathItemReplace = str_replace($snackCase, $replace, $pathItemReplace);
                    $pathItemReplace = str_replace($camelCase, $replace, $pathItemReplace);

                    $nameItemReplace = $item->getName();

                    $nameItemReplace = str_replace($word, $replace, $nameItemReplace);
                    $nameItemReplace = str_replace($snackCase, $replace, $nameItemReplace);
                    $nameItemReplace = str_replace($camelCase, $replace, $nameItemReplace);


                    $result[$item->getPath()] = [
                        'name' => $item->getName(),
                        'nameReplace' => $nameItemReplace,
                        'uuid' => $item->getUuid(),
                        'pathReplace' => $pathItemReplace,
                        'items' => $items_
                    ];
                }
            }
        }

        return $result;
    }

    public function save(array $postData)
    {
        $ch = $postData['ch'] ?? [];
        $replace = $postData['replace'] ?? [];



        foreach ($ch['path'] ?? [] as $path => $val) {

            if (!empty($replace['path'][$path])) {
                foreach ($replace['path'][$path] as $uuid => $value) {
                    $resource = $this->resourceRepository->findOneBy([
                        'uuid' => $uuid,
                    ]);

                    if (!$resource) {
                        continue;
                    }
                    $resource->setPath($value);
                }
                $this->entityManager->flush();
            }

            if (!empty($replace['name'][$path])) {

                foreach ($replace['name'][$path] as $uuid => $value) {

                    $resource = $this->resourceRepository->findOneBy([
                        'uuid' => $uuid,
                    ]);

                    if (!$resource) {
                        continue;
                    }
                    $resource->setName($value);
                }
                $this->entityManager->flush();
            }

            if (!empty($replace['file'][$path])) {
                foreach ($replace['file'][$path]  as $uuid => $contentItems) {
                    $resource = $this->resourceRepository->findOneBy([
                        'uuid' => $uuid,
                    ]);
                    if (!$resource) {
                        continue;
                    }
                    $content = $resource->getContent();
                    if (empty($content)) {
                        continue;
                    }

                    $content = explode("\n", $content);

                    foreach ($contentItems as $lineNumber => $contentItem) {
                        $content[$lineNumber-1] = $contentItem;
                    }
                    $resource->setContent(implode("\n", $content));
                }

                $this->entityManager->flush();
            }
        }
    }
}