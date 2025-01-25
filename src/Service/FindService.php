<?php

namespace App\Service;

use App\Dictionary\TemplatesDictionary;
use App\Entity\User;
use App\Helper\StringHelper;
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
        $replace = strtoupper($replace);
        $result = [];
        $queryBuilder = $this->projectRepository->getOneByUuidQueryBuilder($user, $projectUuid, isFindParents: false);
        $camelCase = s($word)->camel()->toString();
        $snackCase = s($word)->snake()->toString();
        $uppserCase = s($word)->upper()->toString();
        $word = addslashes($word);
        $types = ["TSC", "TSC", "TUC", "TTC"];

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

            $fileName = ($item->getType()->value === 2 ? $item->getName() : '');
            $fileExtension = (!empty($fileName) ?  pathinfo($fileName, PATHINFO_EXTENSION) : '');
            $lan = TemplatesDictionary::LANGS[$fileExtension] ?? null;



            $items_ = [];
            if (!empty($item->getContent())) {
                $file = explode("\n", $item->getContent());
                if (!empty($file)) {
                    $lineNumber = 1;

                    foreach ($file as $fileItem) {
                        $fileItemReplace = $this->find($fileItem, $word, $replace);

                        if (!empty($lan)) {
                            foreach ($lan as $type => $lanItem) {
                                if (
                                    isset($lanItem['FILE_EXTENSION']) &&
                                    in_array( '.'. $fileExtension, $lanItem['FILE_EXTENSION']) &&
                                    isset($lanItem['FIRST_LINE_SYMBOL']) &&
                                    StringHelper::isFirstSymbol($fileItem, $lanItem['FIRST_LINE_SYMBOL'])
                                ) {
                               //     $fileItemReplace = $this->find($fileItemReplace, $word, $replace, $type, force: true);
                                }
                            }
                        }
                        $word = stripslashes($word);

                        $fileItemReplace = $this->find($fileItemReplace, $word, $replace);

                        foreach ($types as $typeItem) {

                        }

                        if ($fileItemReplace !== $fileItem) {
                            $items_[] = [
                                'lineNumber' => $lineNumber,
                                'line' => $fileItem,
                                'lineReplace' => $fileItemReplace,
                                'item' => $item,
                            ];

                        }

                        $lineNumber++;
                    }
                }
            }

            $res = [
                'name' => $item->getName(),
                'nameReplace' => null,
                'uuid' => $item->getUuid(),
                'pathReplace' => null,
                'items' => $items_
            ];

            $pathItemReplace = $this->find($item->getPath(), $word, $replace);
            $pathItemReplace = $this->find($pathItemReplace, $word, $replace, "TSC");
            $pathItemReplace = $this->find($pathItemReplace, $word, $replace, "TSC");
            $pathItemReplace = $this->find($pathItemReplace, $word, $replace, "TUC");
            $pathItemReplace = $this->find($pathItemReplace, $word, $replace, "TTC");

            if ($item->getPath() !== $pathItemReplace) {
                $res['pathItemReplace'] = $pathItemReplace;
            }

            $nameItemReplace = $this->find($item->getName(), $word, $replace);
            $nameItemReplace = $this->find($nameItemReplace, $word, $replace, "TSC");
            $nameItemReplace = $this->find($nameItemReplace, $word, $replace, "TSC");
            $nameItemReplace = $this->find($nameItemReplace, $word, $replace, "TUC");
            $nameItemReplace = $this->find($nameItemReplace, $word, $replace, "TTC");

            if ($item->getName() !== $nameItemReplace) {
                $res['nameReplace'] = $nameItemReplace;
            }

            $result[$item->getPath()] = $res;
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

    private function find(string $fileItem, string $word, string $replace, string $type = 'TCCF', bool $force = false): string
    {
        $result = StringHelper::replaceType($fileItem, $word, $replace);

        return $result;
    }
}