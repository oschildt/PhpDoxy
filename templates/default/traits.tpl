<h1>{header_title}</h1>

<!-- if traits -->
  <table class="table table-responsive table-bordered table-striped multicolumn">
      <tr><th colspan="3">Traits</th></tr>
      
      <!-- foreach traits -->
      <tr>
          <td>
              <a href="{trait_link}">{trait_name}</a>
          </td>
          <td class="namespace_column">
              <!-- if namespace -->
              <a href="{namespace_link}">{namespace}</a>
              <!-- endif namespace -->
          </td>
          <td class="description_column">
              {trait_description}
          </td>
      </tr>
      <!-- endforeach traits -->
      
  </table>
<!-- endif traits -->
