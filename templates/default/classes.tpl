<h1>{header_title}</h1>

<!-- if classes -->
  <table class="table table-responsive table-bordered table-striped multicolumn">
      <tr><th colspan="3">Classes</th></tr>
      
      <!-- foreach classes -->
      <tr>
          <td>
              <a href="{class_link}">{class_name}</a>
          </td>
          <td class="namespace_column">
              <!-- if namespace -->
              <a href="{namespace_link}">{namespace}</a>
              <!-- endif namespace -->
          </td>
          <td class="description_column">
              {class_description}
          </td>
      </tr>
      <!-- endforeach classes -->
      
  </table>
<!-- endif classes -->