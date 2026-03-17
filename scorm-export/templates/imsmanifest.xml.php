<?php
// Variables available: $course (array)
$courseId    = htmlspecialchars($course['id'], ENT_XML1);
$courseTitle = htmlspecialchars($course['metadata']['title'] ?? '', ENT_XML1);
$masteryScore = (int)($course['scorm']['masteryScore'] ?? 80);
$language    = htmlspecialchars($course['metadata']['language'] ?? 'de', ENT_XML1);
$description = htmlspecialchars($course['metadata']['description'] ?? '', ENT_XML1);
?><?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="<?= $courseId ?>"
          version="1.0"
          xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd
                              http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd
                              http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd">

  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>1.2</schemaversion>
    <adlcp:location>imsmanifest.xml</adlcp:location>
  </metadata>

  <organizations default="<?= $courseId ?>_ORG">
    <organization identifier="<?= $courseId ?>_ORG">
      <title><?= $courseTitle ?></title>
      <item identifier="<?= $courseId ?>_ITEM" identifierref="<?= $courseId ?>_RES">
        <title><?= $courseTitle ?></title>
        <adlcp:masteryscore><?= $masteryScore ?></adlcp:masteryscore>
      </item>
    </organization>
  </organizations>

  <resources>
    <resource identifier="<?= $courseId ?>_RES"
              type="webcontent"
              adlcp:scormtype="sco"
              href="index.html">
      <file href="index.html"/>
<?php
// List all media files
$mediaPath = project_root() . '/media/' . $course['id'];
if (is_dir($mediaPath)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($mediaPath, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        $rel = 'media/' . $course['id'] . '/' . str_replace('\\', '/', $iterator->getSubPathName());
        echo '      <file href="' . htmlspecialchars($rel, ENT_XML1) . '"/>' . "\n";
    }
}
?>
    </resource>
  </resources>

</manifest>
