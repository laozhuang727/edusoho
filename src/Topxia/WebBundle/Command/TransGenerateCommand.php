<?php
namespace Topxia\WebBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

use Symfony\Component\Finder\FInder;
use Symfony\Component\Yaml\Yaml;
use Topxia\Common\ArrayToolkit;

class TransGenerateCommand extends BaseCommand
{

    protected function configure()
    {
        $this->setName ( 'trans:generate' )
            ->addArgument('dir', InputArgument::REQUIRED, 'Bundle的目录')
            ->setDescription('生成Bundle的语言文件!');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = $input->getArgument('dir');
        $dir = rtrim($dir, "\/");

        $output->writeln("<comment>正在扫描模版文件的语言串:</comment>");
        $viewKeywords = $this->scanViewTrans($dir, $output);
        $this->printScanResult($viewKeywords, $output);

        $output->writeln("<comment>\n正在扫描菜单配置文件的语言串:</comment>");
        $menuKeywords = $this->scanMenuTrans($dir, $output);
        $this->printScanResult($menuKeywords, $output);

        $output->writeln("<comment>\n语言串总和:</comment>");
        $keywords = array_merge($viewKeywords, $menuKeywords);
        $this->printScanResult($keywords, $output);
        $keywords = array_values(array_unique($keywords));

        $output->writeln("\n<comment>正在生成语言文件:</comment>");

        $locale = $this->getLocale();

        $tranFile = sprintf("%s/%s/Resources/translations/messages.%s.yml", dirname($this->getContainer()->getParameter('kernel.root_dir')), $dir, $locale);

        $existTrans = array();
        if (!file_exists($tranFile)) {
            $output->writeln("创建语言文件 <info>{$tranFile}</info>");
        } else {
            $content = file_get_contents($tranFile);

            $yaml = new Yaml();
            $existTrans = $yaml->parse($content);

            $output->writeln("语言文件 <info>{$tranFile}</info> 已经存在");
        }

        $addCount = 0;
        $existCount = 0;
        foreach ($keywords as $keyword) {
            if (array_key_exists($keyword, $existTrans)) {
                $existCount ++;
            } else {
                $output->writeln(" - {$keyword} <info>... 新增</info>");
                $addCount ++;
                $existTrans[$keyword] = $keyword;
            }
        }

        $output->writeln("<info>已存在{$existCount}个语言串，本次新增{$addCount}个语言串</info>");
        $output->writeln('<question>END</question>');

        $yaml = new Yaml();
        $content = $yaml->dump($existTrans);

        file_put_contents($tranFile, $content);

    }

    protected function getLocale()
    {
        $content = file_get_contents($this->getContainer()->getParameter('kernel.root_dir') . '/config/parameters.yml');

        $matched = preg_match('/locale\s*?\:\s*?(\w+)/', $content, $matches);

        if (!$matched) {
            throw new \RuntimeException('locale未定义!');
        }

        return $matches[1];
    }

    protected function printScanResult($keywords, $output)
    {
        $total = count($keywords);
        $keywords = array_values(array_unique($keywords));
        $uniqueTotal = count($keywords);

        $output->writeln("<info>共找到{$total}个语言串，去除重复语言串后，共有{$uniqueTotal}个语言串。</info>");
    }

    protected function scanMenuTrans($dir, $output)
    {
        $keywords = array();

        $path = realpath($this->getContainer()->getParameter('kernel.root_dir') . '/../' . $dir . '/Resources/config');
        if (empty($path)) {
            $output->writeln("<error>不存在{$dir}/Resources/config目录。</error>");
        }
        $finder = new Finder();
        $finder->files()->in($path)->name('menus_*');
        foreach ($finder as $file) {
            $output->write("{$file->getRealpath()}");
            $yaml = new Yaml();
            $menus = $yaml->parse(file_get_contents($file->getRealpath()));
            $names = ArrayToolkit::column($menus, 'name');
            $fullnames = ArrayToolkit::column($menus, 'fullname');
            $menuKeywords = array_merge($names, $fullnames);
            $keywords = array_merge($keywords, $menuKeywords);
            $output->writeln(sprintf("<info> ... %s</info>", count($menuKeywords)));
        }

        return $keywords;
    }

    protected function scanViewTrans($dir, $output)
    {
        $keywords = array();

        $path = realpath($this->getContainer()->getParameter('kernel.root_dir') . '/../' . $dir . '/Resources/views');
        if (empty($path)) {
            $output->write("<error>{$dir}/Resources/views is not exist.</error>");
        }

        $finder = new Finder();
        $finder->files()->in($path);
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealpath());

            $matched = preg_match_all('/\{\{\s*\'(.*?)\'\s*\|\s*?trans.*?\}\}/', $content, $matches);

            if ($matched) {
                $output->write("{$file->getRealpath()}");
                $count = count($matches[1]);
                $output->writeln("<info> ... {$count}</info>");

                $keywords = array_merge($keywords, $matches[1]);
            }
        }

        return $keywords;
    }

}