<?php

namespace IPublishingjp\GooglePlayReporter;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Snscripts\Result\Result;

class Reporter
{
    protected $projectId = '';
    protected $keyFilePath = '';
    protected $bucketName = '';

    /**
     * @var ?Bucket
     */
    private $bucket;

    /**
     * @var ?Result
     */
    protected $lastResult = null;

    /**
     * get the last result set
     *
     * @return ?Result
     */
    public function getLastResult(): ?Result
    {
        return $this->lastResult;
    }

    /**
     * @param string $projectId
     */
    public function setProjectId(string $projectId)
    {
        if ($this->projectId !== $projectId) {
            $this->resetBucket();
            $this->projectId = $projectId;
        }
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    /**
     * @param string $keyFilePath
     */
    public function setKeyFilePath(string $keyFilePath)
    {
        if ($this->keyFilePath !== $keyFilePath) {
            $this->resetBucket();
            $this->keyFilePath = $keyFilePath;
        }
    }

    public function getKeyFilePath(): string
    {
        return $this->keyFilePath;
    }

    /**
     * @param string $bucketName
     */
    public function setBucketName(string $bucketName)
    {
        if ($this->bucketName !== $bucketName) {
            $this->resetBucket();
            $this->bucketName = $bucketName;
        }
    }

    public function getBucketName(): string
    {
        return $this->bucketName;
    }

    protected function createBucketIfNeeded(): Bucket
    {
        if (!$this->bucket) {
            $storageClient = new StorageClient([
                'projectId' => $this->projectId,
                'keyFilePath' => $this->keyFilePath,
            ]);
            $this->bucket = $storageClient->bucket($this->bucketName);
        }

        return $this->bucket;
    }

    protected function resetBucket()
    {
        $this->bucket = null;
    }

    /**
     * @param string $package
     * @param string $reportType
     * @param int $year
     * @param int $month
     * @return array
     */
    public function getSalesReport(string $package, string $reportType, int $year, int $month)
    {
        try {
            $bucket = $this->createBucketIfNeeded();

            $name = sprintf('stats/installs/installs_%s_%d%02d_%s.csv', $package, $year, $month, $reportType);

            $object = $bucket->object($name);

            $contents = $object->downloadAsString();
            $encodedContents = mb_convert_encoding($contents, 'UTF-8', 'UTF-16');

            $temp = tmpfile();

            fwrite($temp, $encodedContents);
            rewind($temp);

            $head = true;

            $header = null;
            $numberOfColumns = 0;
            $sales = [];

            while ($data = fgetcsv($temp)) {
                if ($head) {
                    $header = $data;
                    $numberOfColumns = count($header);
                    $head = false;
                } else {
                    $sale = [];

                    for ($i = 0; $i < $numberOfColumns; $i++) {
                        $sale[$header[$i]] = $data[$i];
                    }

                    $sales[] = $sale;
                }
            }

            fclose($temp);

            $this->lastResult = new Result(Result::SUCCESS);

            return $sales;
        } catch (\Exception $e) {
            $this->lastResult = Result::fail(
                Result::ERROR,
                $e->getMessage(),
                [$e],
                [],
                $e
            );

            return [];
        }
    }
}
