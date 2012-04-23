<?php

class ViewPaginator extends DataObjectSet {
   
   /**
    * Take the unique key of a translated string and render it as a
    * Pagination Description template. This is a workaround for the
    * limits imposed by SilverStripes template/sprintf system.
    *
    * Example templates:
    *    "Showing Page {{ CurrentPage }}" -> "Showing Page 2"
    *    "Showing {{ FirstItem }}-{{ LastItem }} of {{ TotalItems }}" -> "Showing 1-10 of 500"
    *
    * @param string $tKey The unique ID of a translated string (for use with _t()).
    * @return string Rendered Template
    */
   public function PaginationDescription($tKey) {
      $tmpl = _t($tKey);
      
      $tmplReplacements = array(
         'FirstItem' => $this->FirstItem(),
         'LastItem' => $this->LastItem(),
         'TotalItems' => $this->TotalItems(),
         'CurrentPage' => $this->CurrentPage(),
         'TotalPages' => $this->TotalPages());
      $tmplVals = array_values($tmplReplacements);
      
      // Render Short Hand Template e.g. "Showing Page %4$s" becomes "Showing Page 2"
      $descr = preg_replace_callback('/\%(?P<index>[0-9]+)\$([\w])/', function($match) use ($tmplVals) {
         return $tmplVals[$match['index'] - 1];
      }, $tmpl);
      
      // Render Long Hand  e.g. "Showing Page {{ CurrentPage }}" becomes "Showing Page 2"
      $descr = preg_replace_callback('/\{\{[\s]*(?P<key>[\w]+)[\s]*\}\}/', function($match) use ($tmplReplacements) {
         return $tmplReplacements[$match['key']];
      }, $descr);
      
      return $descr;
   }

}
