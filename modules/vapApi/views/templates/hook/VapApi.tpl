HELlo from comment product module {$message}
<form action="{$smarty.server.HTTP_HOST}{$smarty.server.REQUEST_URI}" method="post">
      <fieldset class="form-groupe">
          <label class="form-control-label" for="exempleInput1">Rentrez votre commentaire</label>
          <textarea required name="comment" class="form-control"  id="comment" cols="10" rows="5"></textarea>
      </fieldset>
          <br>
          <input type="submit" class="btn btn-primary-outline" value="Submit">
      </form>