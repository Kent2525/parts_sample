<?php
//select文
//別名
//配列を上げる。call_user_func_array
//INNER JOIN
//json_decode
namespace App\Classes\Daos;

use App\Classes\Constants;
use Illuminate\Support\Facades\DB;

// ------------------------------------------------------------------------------ //
class WorkDao implements Constants
{
    // -----------------------------------------------------------------------//
    public function getPartnerAndPlaceById($partner_id)
    {
      $query = <<<SQL
          SELECT --ここで取得するカラムを指定する
            part.place_id --partnersテーブルのplace_id
            ,pl.place_name --placesテーブルのplace_name
            ,part.company_id --partnersテーブルのcompany_id
            ,com.name AS 'company_name' --AS以降の文字列は実際に取得する際のKey名
            ,part.id AS 'partner_id'
          FROM --テーブルを指定
            partners part
          INNER JOIN companies com --テーブルを指定
            ON com.id = part.company_id AND com.deleted = 0 -- 左辺=右辺が一致したレコード及びcompaniesのdeleted =0を取得
          INNER JOIN places pl
            ON pl.id = part.place_id AND pl.deleted = 0
          WHERE
            part.id = ? --リクエストできた値のみを検索
            AND part.deleted = 0 --FROMで書かれたpartnersテーブルの取得条件はここに書く。
            AND part.invite_status = 1
      ;
      SQL;
        $values = [$partner_id];

        $result_set = DB::connection(self::CONN_KEY_WORK)->selectOne($query, $values);
        $result = json_decode(collect($result_set), true);

        return $result;
    }

    // -------------------------------------------------------------------------- //
    public function getSiteAndPlaceById($site_sync)
    {
      $query = <<<SQL
            SELECT
              pl.id AS 'place_id' , 
              pl.place_name ,
              s.id AS 'site_id',
              s.name
            FROM
              sites s
            INNER JOIN places pl
              ON s.place_id = pl.id AND pl.deleted = 0
            WHERE
              s.id = ?
              AND s.deleted = 0
            ;
            SQL;

      $values = [$site_sync];
      $result_set = DB::connection(self::CONN_KEY_WORK)->select($query, $values);
      $result_json = json_decode(collect($result_set), true);
      // 配列が深くなってしまうので階層を上げる。
      $result =call_user_func_array("array_merge", $result_json);

      return $result;
    }
  }