<?php
namespace KimaiPlugin\NeontribeCvsImportBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class CsvUploadForm extends AbstractType {

  public function buildForm(FormBuilderInterface $builder, array $options) {
    $builder->add('chunk', IntegerType::class, [
      'label' => 'Records per action',
      'data' => 50,
      'help' => 'The number of sheets to create each parse of the file.  If you are hitting memory limits lower this number'
    ]);

    $builder->add('dryrun', CheckboxType::class, [
      'label' => 'Dry run',
      'required' => False,
      'data' => False,
      'help' => 'Process the file but don\'t actually create the time sheet record.'
    ]);

    $builder->add('checkhashes', CheckboxType::class, [
      'label' => 'Check hashes',
      'required' => False,
      'data' => False,
      'help' => 'If checked each line of the cvs will be hashed and checked against hashes of previously imported records.  Duplicates will be ignored.'
    ]);

    $builder->add('checkids', CheckboxType::class, [
      'label' => 'Check timesheet ids',
      'required' => False,
      'data' => False,
      'help' => 'If checked it will be assumed that column index zero is a uid for the timesheet record that will be checked against ids of previously imported records.  Duplicates will be ignored..'
    ]);

    $builder->add('csvfile', FileType::class, [
      'label' => 'Choose file'
    ]);
  }

  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults([ // Configure your form options here
    ]);
  }
}
