<?php

namespace App\Admin\Controllers\wx;

use App\Admin\Extensions\OrderExcelExporter;
use App\Models\Order;
use App\Models\OrderItem;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Request;

class OrderController extends AdminController
{
    use ValidatesRequests;
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '商品订单';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Order());
//        $grid->model()->getQueryBuilder()->dd();

        $grid->column('id', __('Id'));
        $grid->column('no', __('订单编号'));
        $grid->column('user.name', __('用户名'));
        $grid->column('address', __('地址'));
        $grid->column('total_amount', __('总价'));
        $grid->column('remark', __('备注'));
        $grid->column('paid_at', __('支付时间'));
        $grid->column('payment_method', __('支付方式'));
        $grid->column('payment_no', __('流水号'));
        $grid->column('refund_status', __('退款退货状态'));
        $grid->column('refund_no', __('退款退货单号'));
        $grid->column('closed', __('是否关闭'));
        $grid->column('reply_status', __('是否评价'));
        $grid->column('cancel', __('是否取消'));
        $grid->column('ship_status', __('物流状态'));
        $grid->column('ship_data', __('物流信息'));
        $grid->column('extra', __('其他数据'));
        $grid->column('created_at', __('创建时间'));

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableDelete();
//            $actions->disableView();
//            $actions->add(new ModelList($actions->getKey(), 'order.info.list', '订单详情'));
        });
        $grid->filter(function (Grid\Filter $filter) {
            $filter->disableIdFilter();
            $filter->like('no', '编号');
            $filter->like('user.name', '会员名');
        });
        $grid->disableCreateButton();
        $grid->exporter(new OrderExcelExporter());
        return $grid;
    }

    public function infoList($id, Content $content) {
        return $content
            ->title($this->title())
            ->description($this->description['index'] ?? trans('admin.list'))
            ->body($this->InfoGrid($id));
    }

    public function InfoGrid($order_id) {
        $grid = new Grid(new OrderItem());
        $grid->model()->where('order_id', $order_id);

        $grid->column('goods.title', __('商品名称'));
        $grid->column('amount', __('数量'));
        $grid->column('price', __('单价'));
        $grid->column('rating', __('评分'));
        $grid->column('goods.express_price', __('市场价'));
        $grid->column('goods.price', __('售价'));

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableAll();
        });
        $grid->disableRowSelector();
        $grid->disableExport();
        $grid->disableCreateButton();
        $grid->disableFilter();
        return $grid;
    }

    public function show($id, Content $content)
    {
        return $content
            ->header('查看订单')
            // body 方法可以接受 Laravel 的视图作为参数
            ->body(view('admin.orders.show', ['order' => Order::find($id)]));
    }

    public function ship(Order $order, \Illuminate\Http\Request $request)
    {
        // 判断当前订单是否已支付
        if (!$order->paid_at) {
            abort(403,'该订单未付款');
        }
        // 判断当前订单发货状态是否为未发货
        if ($order->ship_status !== Order::SHIP_STATUS_PENDING) {
            abort(403,'该订单已发货');
        }
        // Laravel 5.5 之后 validate 方法可以返回校验过的值
        $data = $this->validate($request, [
            'express_company' => ['required'],
            'express_no'      => ['required'],
        ], [], [
            'express_company' => '物流公司',
            'express_no'      => '物流单号',
        ]);
        // 将订单发货状态改为已发货，并存入物流信息
        $order->update([
            'ship_status' => Order::SHIP_STATUS_DELIVERED,
            // 我们在 Order 模型的 $casts 属性里指明了 ship_data 是一个数组
            // 因此这里可以直接把数组传过去
            'ship_data'   => $data,
        ]);

        // 返回上一页
        return redirect()->back();
    }
    public function received(Order $order, Request $request)
    {
        // 判断订单的发货状态是否为已发货
        if ($order->ship_status !== Order::SHIP_STATUS_DELIVERED) {
            abort(403,'未发货');
        }

        // 更新发货状态为已收到
        $order->update(['ship_status' => Order::SHIP_STATUS_RECEIVED]);

        // 返回原页面
        return response(null,201);
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Order::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('no', __('No'));
        $show->field('user_id', __('User id'));
        $show->field('address', __('Address'));
        $show->field('total_amount', __('Total amount'));
        $show->field('remark', __('Remark'));
        $show->field('paid_at', __('Paid at'));
        $show->field('payment_method', __('Payment method'));
        $show->field('payment_no', __('Payment no'));
        $show->field('refund_status', __('Refund status'));
        $show->field('refund_no', __('Refund no'));
        $show->field('closed', __('Closed'));
        $show->field('reply_status', __('Reply status'));
        $show->field('cancel', __('Cancel'));
        $show->field('ship_status', __('Ship status'));
        $show->field('ship_data', __('Ship data'));
        $show->field('extra', __('Extra'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Order());

        $form->text('no', __('订单编号'))->disable();
        $form->text('user.name', __('用户名'))->disable();
        $form->textarea('address', __('地址'))->disable();
        $form->decimal('total_amount', __('总价'))->disable();
        $form->textarea('remark', __('备注'))->disable();
        $form->datetime('paid_at', __('支付时间'))->disable();
        $form->text('payment_method', __('支付方式'))->disable();
//        $form->text('payment_no', __('流水号'))->disable();
        $form->text('refund_status', __('退款退货状态'))->default('refund_pending');
        $form->text('refund_no', __('退款退货单号'));
        $form->switch('closed', __('是否关闭'));
//        $form->switch('reply_status', __('是否已评价'))->disable();
//        $form->switch('cancel', __('是否取消'))->disable();
        $form->text('ship_status', __('物流状态'))->default('ship_pending');
        $form->textarea('ship_data', __('物流信息'));
        $form->textarea('extra', __('其他数据'));

        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        return $form;
    }
}